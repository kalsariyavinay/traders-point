<?php

namespace Tests\Unit;

use App\Services\RazorpayGateway;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RazorpayGatewayTest extends TestCase
{
    private function gateway(?string $key = 'rzp_test_key', ?string $secret = 'test_secret'): RazorpayGateway
    {
        return new RazorpayGateway($key, $secret);
    }

    public function test_it_reports_whether_keys_are_present(): void
    {
        $this->assertTrue($this->gateway()->isConfigured());
        $this->assertFalse($this->gateway(null, null)->isConfigured());
        $this->assertFalse($this->gateway('rzp_test_key', '')->isConfigured());
        $this->assertFalse($this->gateway('', 'test_secret')->isConfigured());
    }

    public function test_it_defaults_to_rupees(): void
    {
        $this->assertSame('INR', $this->gateway()->currency());
    }

    public function test_it_accepts_a_signature_razorpay_would_have_produced(): void
    {
        $signature = hash_hmac('sha256', 'order_ABC|pay_XYZ', 'test_secret');

        $this->assertTrue($this->gateway()->verifyPayment('order_ABC', 'pay_XYZ', $signature));
    }

    public function test_it_rejects_a_signature_made_with_the_wrong_secret(): void
    {
        $signature = hash_hmac('sha256', 'order_ABC|pay_XYZ', 'wrong_secret');

        $this->assertFalse($this->gateway()->verifyPayment('order_ABC', 'pay_XYZ', $signature));
    }

    public function test_it_rejects_a_signature_lifted_from_another_order(): void
    {
        $signature = hash_hmac('sha256', 'order_OTHER|pay_XYZ', 'test_secret');

        $this->assertFalse($this->gateway()->verifyPayment('order_ABC', 'pay_XYZ', $signature));
    }

    public function test_it_refuses_to_call_the_api_without_keys(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('RAZORPAY_KEY');

        $this->gateway(null, null)->createOrder(10000, 'receipt-1');
    }
}
