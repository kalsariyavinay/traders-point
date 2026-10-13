<?php

namespace App\Http\Controllers;

use App\Actions\FulfilPaidOrder;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\RazorpayGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly RazorpayGateway $gateway,
        private readonly FulfilPaidOrder $fulfil,
    ) {}

    /**
     * Where Razorpay's checkout handler lands. Nothing here trusts the browser:
     * the order is looked up by its Razorpay id, matched to the signed-in user,
     * and the signature is re-derived from the API secret before access is given.
     */
    public function success(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:255'],
            'razorpay_payment_id' => ['required', 'string', 'max:255'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        $order = Order::query()
            ->with('video')
            ->where('razorpay_order_id', $payload['razorpay_order_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $order) {
            return to_route('home')
                ->with('error', 'We could not match that payment to an order. Nothing was charged twice — contact us if money left your account.');
        }

        // Razorpay can call back more than once; granting access twice is a no-op.
        if ($order->isPaid()) {
            return to_route('dashboard')
                ->with('success', 'That purchase was already confirmed. It is in your library.');
        }

        // Blank keys are the documented default, and verifyPayment() throws
        // rather than returning false, so without this an ordinary learner
        // gets a white 500 page instead of being told the payment failed.
        if (! $this->gateway->isConfigured()) {
            report(new RuntimeException('payment-success was reached with no Razorpay keys configured.'));

            return to_route('videos.show', $order->video)
                ->with('error', 'Payments are not switched on yet, so that purchase could not be confirmed. Nothing was charged.');
        }

        $verified = $this->gateway->verifyPayment(
            $payload['razorpay_order_id'],
            $payload['razorpay_payment_id'],
            $payload['razorpay_signature'],
        );

        if (! $verified) {
            $order->forceFill([
                'status' => OrderStatus::Failed,
                'razorpay_payment_id' => $payload['razorpay_payment_id'],
            ])->save();

            return to_route('videos.show', $order->video)
                ->with('error', 'We could not verify that payment. No access was granted. If you were charged, contact us and we will sort it out.');
        }

        // Shared with the webhook, so whichever arrives first wins and the
        // second one is a no-op.
        ($this->fulfil)($order, $payload['razorpay_payment_id']);

        return to_route('dashboard')
            ->with('success', '"'.$order->video->title.'" is unlocked. It stays in your library.');
    }

    /**
     * Razorpay's payment.failed event, so a declined card does not sit in the
     * admin's orders table as "pending" forever.
     */
    public function failed(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $order = Order::query()
            ->with('video')
            ->where('razorpay_order_id', $payload['razorpay_order_id'])
            ->where('user_id', $request->user()->id)
            ->where('status', OrderStatus::Pending)
            ->first();

        $order?->forceFill(['status' => OrderStatus::Failed])->save();

        return back()->with('error', 'The payment did not go through. You have not been charged — you can try again.');
    }
}
