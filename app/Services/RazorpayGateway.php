<?php

namespace App\Services;

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Razorpay\Api\Utility;
use RuntimeException;

/**
 * Everything that talks to Razorpay goes through here, so the controllers stay
 * free of SDK details and tests can swap the one method that needs the network.
 */
class RazorpayGateway
{
    public function __construct(
        private readonly ?string $key,
        private readonly ?string $secret,
        private readonly string $currency = 'INR',
        private readonly ?string $webhookSecret = null,
    ) {}

    /**
     * False until real API keys are in .env, which keeps the buy button from
     * opening a checkout that cannot work.
     */
    public function isConfigured(): bool
    {
        return filled($this->key) && filled($this->secret);
    }

    public function key(): string
    {
        return (string) $this->key;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * Create the order on Razorpay's side and return its id.
     *
     * @param  int  $amountInPaise  Razorpay bills in the smallest currency unit.
     * @param  array<string, scalar>  $notes
     */
    public function createOrder(int $amountInPaise, string $receipt, array $notes = []): string
    {
        $order = $this->api()->order->create([
            'amount' => $amountInPaise,
            'currency' => $this->currency,
            'receipt' => $receipt,
            'payment_capture' => 1,
            'notes' => $notes,
        ]);

        return (string) $order['id'];
    }

    /**
     * Confirm the checkout response really came from Razorpay. The SDK HMACs
     * "{order_id}|{payment_id}" with the API secret, so this needs no network.
     */
    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool
    {
        try {
            $this->api()->utility->verifyPaymentSignature([
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
    }

    /**
     * Webhooks are signed with their own secret, set in the Razorpay dashboard
     * alongside the endpoint URL. Without it we cannot trust a single call.
     */
    public function isWebhookConfigured(): bool
    {
        return filled($this->webhookSecret);
    }

    /**
     * Confirm a webhook body really came from Razorpay. The signature covers the
     * RAW request body, so the caller must pass it unparsed and unmodified.
     */
    public function verifyWebhook(string $payload, ?string $signature): bool
    {
        if (! $this->isWebhookConfigured() || blank($signature)) {
            return false;
        }

        try {
            // verifyWebhookSignature takes the secret directly, so no API keys
            // are needed and nothing leaves this machine.
            (new Utility)->verifyWebhookSignature($payload, $signature, $this->webhookSecret);

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
    }

    private function api(): Api
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Razorpay API keys are missing. Set RAZORPAY_KEY and RAZORPAY_SECRET in .env.');
        }

        // The constructor also seeds the static secret that Utility reads.
        return new Api($this->key, $this->secret);
    }
}
