<?php

namespace Tests\Feature;

use App\Actions\FulfilPaidOrder;
use App\Mail\PurchaseReceipt;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PurchaseReceiptTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => self::SECRET,
            'services.razorpay.webhook_secret' => 'whsec_test',
        ]);
    }

    private function pendingOrder(): Order
    {
        return Order::factory()
            ->for(User::factory()->create(['name' => 'Asha Patel', 'email' => 'asha@example.com']))
            ->for(Video::factory()->paid(1499.00)->create(['title' => 'Options Greeks Explained']))
            ->create(['razorpay_order_id' => 'order_ABC123', 'amount' => 1499.00]);
    }

    public function test_fulfilment_sends_a_receipt_to_the_buyer(): void
    {
        $order = $this->pendingOrder();

        app(FulfilPaidOrder::class)($order, 'pay_XYZ789');

        Mail::assertQueued(PurchaseReceipt::class, function (PurchaseReceipt $mail) use ($order) {
            return $mail->hasTo($order->user->email)
                && $mail->order->is($order);
        });
    }

    public function test_the_receipt_is_queued_rather_than_sent_inline(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new PurchaseReceipt($this->pendingOrder()));
    }

    public function test_an_already_paid_order_does_not_send_a_second_receipt(): void
    {
        $order = $this->pendingOrder();

        app(FulfilPaidOrder::class)($order, 'pay_XYZ789');
        app(FulfilPaidOrder::class)($order, 'pay_XYZ789');

        Mail::assertQueuedCount(1);
    }

    public function test_the_browser_callback_triggers_the_receipt(): void
    {
        $order = $this->pendingOrder();

        $this->actingAs($order->user)->post(route('payment.success'), [
            'razorpay_order_id' => 'order_ABC123',
            'razorpay_payment_id' => 'pay_XYZ789',
            'razorpay_signature' => hash_hmac('sha256', 'order_ABC123|pay_XYZ789', self::SECRET),
        ])->assertRedirect(route('dashboard'));

        Mail::assertQueued(PurchaseReceipt::class);
    }

    public function test_the_webhook_triggers_the_receipt_too(): void
    {
        $order = $this->pendingOrder();

        $body = json_encode([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_XYZ789', 'order_id' => 'order_ABC123', 'amount' => 149900,
            ]]],
        ]);

        $this->call('POST', route('razorpay.webhook'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'whsec_test'),
        ], content: $body)->assertOk();

        Mail::assertQueued(PurchaseReceipt::class);
        $this->assertTrue($order->user->fresh()->hasPurchased($order->video));
    }

    public function test_a_failed_payment_sends_nothing(): void
    {
        $order = $this->pendingOrder();

        $this->actingAs($order->user)->post(route('payment.success'), [
            'razorpay_order_id' => 'order_ABC123',
            'razorpay_payment_id' => 'pay_XYZ789',
            'razorpay_signature' => 'forged',
        ]);

        Mail::assertNothingQueued();
    }

    public function test_the_receipt_states_the_amount_video_and_payment_id(): void
    {
        $order = $this->pendingOrder();
        app(FulfilPaidOrder::class)($order, 'pay_XYZ789');

        $rendered = (new PurchaseReceipt($order->fresh(['user', 'video.category', 'coupon'])))->render();

        $this->assertStringContainsString('Asha Patel', $rendered);
        $this->assertStringContainsString('Options Greeks Explained', $rendered);
        $this->assertStringContainsString('1,499.00', $rendered);
        $this->assertStringContainsString('pay_XYZ789', $rendered);
        $this->assertStringContainsString(route('dashboard'), $rendered);
    }

    public function test_the_subject_names_the_lesson(): void
    {
        $order = $this->pendingOrder();

        $this->assertSame(
            'Your receipt for "Options Greeks Explained"',
            (new PurchaseReceipt($order))->envelope()->subject,
        );
    }

    public function test_a_discounted_receipt_shows_the_coupon_and_list_price(): void
    {
        $coupon = Coupon::factory()->percent(25)->create(['code' => 'DIWALI25']);
        $order = $this->pendingOrder();
        $order->update(['coupon_id' => $coupon->id, 'amount' => 1124.25, 'discount_amount' => 374.75]);

        app(FulfilPaidOrder::class)($order, 'pay_XYZ789');

        $rendered = (new PurchaseReceipt($order->fresh(['user', 'video.category', 'coupon'])))->render();

        $this->assertStringContainsString('DIWALI25', $rendered);
        $this->assertStringContainsString('374.75', $rendered);
        $this->assertStringContainsString('1,499.00', $rendered);   // list price restored
        $this->assertStringContainsString('1,124.25', $rendered);   // actually charged
    }

    public function test_a_full_price_receipt_leaves_out_the_discount_rows(): void
    {
        $order = $this->pendingOrder();
        app(FulfilPaidOrder::class)($order, 'pay_XYZ789');

        $rendered = (new PurchaseReceipt($order->fresh(['user', 'video.category', 'coupon'])))->render();

        $this->assertStringNotContainsString('Discount', $rendered);
        $this->assertStringNotContainsString('List price', $rendered);
    }
}
