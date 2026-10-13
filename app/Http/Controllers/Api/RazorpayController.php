<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Video;
use App\Services\RazorpayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class RazorpayController extends Controller
{
    /** Razorpay rejects orders below one rupee. */
    private const MINIMUM_CHARGE = 1.00;

    public function __construct(private readonly RazorpayGateway $gateway) {}

    /**
     * Open a checkout for one video: record a pending order, create the matching
     * Razorpay order, and hand the browser everything the checkout modal needs.
     *
     * The amount is always read from the video row, never from the request, so a
     * tampered client cannot set its own price.
     */
    public function store(Request $request, Video $video): JsonResponse
    {
        $user = $request->user();

        abort_unless($video->isPublished(), 404);

        if ($video->is_free) {
            return response()->json([
                'message' => 'This video is free to watch — no payment needed.',
            ], 422);
        }

        if ($user->hasPurchased($video)) {
            return response()->json([
                'message' => 'You already own this video.',
            ], 409);
        }

        if (! $this->gateway->isConfigured()) {
            return response()->json([
                'message' => 'Payments are not switched on yet. Please try again later.',
            ], 503);
        }

        // Optional coupon. Everything about it is decided here: the browser only
        // ever hands over a code string.
        $coupon = null;
        $listPrice = (float) $video->price;
        $discount = 0.0;

        if (filled($request->input('coupon_code'))) {
            $coupon = Coupon::findByCode((string) $request->input('coupon_code'));

            if (! $coupon || ! $coupon->isUsable()) {
                return response()->json([
                    'message' => 'That coupon is not valid any more.',
                ], 422);
            }

            $discount = $coupon->discountOn($listPrice);
        }

        // Razorpay will not take an order below ₹1, so that is the floor.
        $payable = max(self::MINIMUM_CHARGE, round($listPrice - $discount, 2));
        $discount = round($listPrice - $payable, 2);

        // Reuse the user's open attempt for this video rather than piling up rows.
        $order = Order::query()
            ->where('user_id', $user->id)
            ->where('video_id', $video->id)
            ->where('status', OrderStatus::Pending)
            ->first() ?? new Order(['user_id' => $user->id, 'video_id' => $video->id]);

        $order->fill([
            'amount' => $payable,
            'discount_amount' => $discount,
            'coupon_id' => $coupon?->id,
            'status' => OrderStatus::Pending,
            'razorpay_payment_id' => null,
        ])->save();

        try {
            $razorpayOrderId = $this->gateway->createOrder(
                amountInPaise: $order->amountInPaise(),
                receipt: 'tpa-order-'.$order->id,
                notes: [
                    'order_id' => (string) $order->id,
                    'video_id' => (string) $video->id,
                    'user_id' => (string) $user->id,
                ],
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'We could not reach Razorpay. Please try again in a moment.',
            ], 502);
        }

        $order->forceFill(['razorpay_order_id' => $razorpayOrderId])->save();

        return response()->json([
            'key' => $this->gateway->key(),
            'order_id' => $razorpayOrderId,
            'amount' => $order->amountInPaise(),
            'currency' => $this->gateway->currency(),
            'name' => config('academy.brand'),
            'description' => $video->title,
            'prefill' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'notes' => [
                'video' => $video->slug,
            ],
            'theme' => [
                'color' => '#E8B339',
            ],
            'coupon' => $coupon ? [
                'code' => $coupon->code,
                'discount_percent' => $coupon->discount_percent,
                'discount_amount' => number_format($discount, 2, '.', ''),
                'list_price' => number_format($listPrice, 2, '.', ''),
            ] : null,
        ]);
    }
}
