<?php

namespace App\Http\Controllers\Api;

use App\Actions\FulfilPaidOrder;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RazorpayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay's server-to-server notification.
 *
 * This is the safety net for the checkout: if the buyer's browser dies between
 * paying and hitting /payment-success, this still unlocks the video. It is
 * CSRF-exempt and unauthenticated, so the signature is the only thing standing
 * between a caller and someone else's purchase.
 *
 * Non-2xx answers make Razorpay retry, so anything we cannot act on is
 * acknowledged with 200 and logged instead.
 */
class RazorpayWebhookController extends Controller
{
    public function __construct(
        private readonly RazorpayGateway $gateway,
        private readonly FulfilPaidOrder $fulfil,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->gateway->isWebhookConfigured()) {
            Log::warning('Razorpay webhook received but RAZORPAY_WEBHOOK_SECRET is not set.');

            return response()->json(['message' => 'Webhook not configured.'], 503);
        }

        // The signature covers the raw body, so verify before touching the parsed input.
        if (! $this->gateway->verifyWebhook($request->getContent(), $request->header('X-Razorpay-Signature'))) {
            Log::warning('Razorpay webhook rejected: bad signature.', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $event = (string) $request->input('event');

        if ($event !== 'payment.captured') {
            return response()->json(['message' => "Ignored event [{$event}]."]);
        }

        $payment = (array) $request->input('payload.payment.entity', []);
        $razorpayOrderId = $payment['order_id'] ?? null;
        $razorpayPaymentId = $payment['id'] ?? null;
        $capturedPaise = (int) ($payment['amount'] ?? 0);

        if (blank($razorpayOrderId) || blank($razorpayPaymentId)) {
            Log::warning('Razorpay payment.captured missing order_id or payment id.', $payment);

            return response()->json(['message' => 'Incomplete payload.']);
        }

        $order = Order::with(['user', 'video'])
            ->where('razorpay_order_id', $razorpayOrderId)
            ->first();

        if (! $order) {
            // Nothing we can do on a retry either -- a foreign or long-deleted order.
            Log::warning('Razorpay payment.captured for an unknown order.', [
                'razorpay_order_id' => $razorpayOrderId,
            ]);

            return response()->json(['message' => 'Order not found.']);
        }

        // Never unlock for less than the price we recorded.
        if ($capturedPaise < $order->amountInPaise()) {
            Log::error('Razorpay captured less than the order amount; access withheld.', [
                'order_id' => $order->id,
                'expected_paise' => $order->amountInPaise(),
                'captured_paise' => $capturedPaise,
            ]);

            return response()->json(['message' => 'Amount mismatch.']);
        }

        $granted = ($this->fulfil)($order, $razorpayPaymentId);

        Log::info($granted
            ? 'Razorpay webhook unlocked an order.'
            : 'Razorpay webhook saw an order that was already paid.', ['order_id' => $order->id]);

        return response()->json([
            'message' => $granted ? 'Access granted.' : 'Already fulfilled.',
        ]);
    }
}
