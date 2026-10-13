<?php

namespace Tests\Feature\Checkout;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use App\Services\RazorpayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The browser only ever sends a code string. Every rupee is worked out here.
 */
class CouponTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'test_secret',
        ]);

        $this->user = User::factory()->create();

        $this->mock(RazorpayGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('key')->andReturn('rzp_test_key');
            $mock->shouldReceive('currency')->andReturn('INR');
            $mock->shouldReceive('createOrder')->andReturn('order_ABC123');
        });
    }

    /**
     * The mock above stands in for the one call that needs the network. Once an
     * order exists, swap the real gateway back so signature checks are genuine
     * rather than a double repeating the same HMAC.
     */
    private function useRealGateway(): void
    {
        $this->app->forgetInstance(RazorpayGateway::class);
        $this->app->instance(RazorpayGateway::class, new RazorpayGateway(
            'rzp_test_key',
            'test_secret',
            'INR',
            config('services.razorpay.webhook_secret'),
        ));
    }

    public function test_a_valid_coupon_reduces_the_amount_sent_to_razorpay(): void
    {
        $video = Video::factory()->paid(1000.00)->create();
        Coupon::factory()->percent(25)->create(['code' => 'DIWALI25']);

        $response = $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video), ['coupon_code' => 'DIWALI25']);

        $response->assertOk()
            ->assertJsonPath('amount', 75000)                       // ₹750 in paise
            ->assertJsonPath('coupon.code', 'DIWALI25')
            ->assertJsonPath('coupon.discount_percent', 25)
            ->assertJsonPath('coupon.discount_amount', '250.00');

        $order = Order::sole();
        $this->assertSame('750.00', $order->amount);
        $this->assertSame('250.00', $order->discount_amount);
        $this->assertNotNull($order->coupon_id);
    }

    public function test_the_code_is_case_and_space_insensitive(): void
    {
        $video = Video::factory()->paid(1000.00)->create();
        Coupon::factory()->percent(25)->create(['code' => 'DIWALI25']);

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video), ['coupon_code' => '  diwali25 '])
            ->assertOk()
            ->assertJsonPath('amount', 75000);
    }

    public function test_no_coupon_still_charges_the_list_price(): void
    {
        $video = Video::factory()->paid(1000.00)->create();

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video))
            ->assertOk()
            ->assertJsonPath('amount', 100000)
            ->assertJsonPath('coupon', null);

        $order = Order::sole();
        $this->assertSame('0.00', $order->discount_amount);
        $this->assertNull($order->coupon_id);
    }

    public function test_an_unknown_code_is_refused_rather_than_ignored(): void
    {
        $video = Video::factory()->paid(1000.00)->create();

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video), ['coupon_code' => 'NOPE'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'That coupon is not valid any more.');

        $this->assertDatabaseEmpty('orders');
    }

    public function test_an_expired_coupon_is_refused(): void
    {
        $video = Video::factory()->paid(1000.00)->create();
        Coupon::factory()->expired()->create(['code' => 'OLD']);

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video), ['coupon_code' => 'OLD'])
            ->assertStatus(422);

        $this->assertDatabaseEmpty('orders');
    }

    public function test_a_used_up_coupon_is_refused(): void
    {
        $video = Video::factory()->paid(1000.00)->create();
        Coupon::factory()->exhausted()->create(['code' => 'GONE']);

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video), ['coupon_code' => 'GONE'])
            ->assertStatus(422);
    }

    public function test_an_unlimited_coupon_keeps_working(): void
    {
        $video = Video::factory()->paid(1000.00)->create();
        Coupon::factory()->create(['code' => 'ALWAYS', 'max_uses' => null, 'used_count' => 9999]);

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video), ['coupon_code' => 'ALWAYS'])
            ->assertOk();
    }

    public function test_a_full_discount_still_respects_the_one_rupee_floor(): void
    {
        $video = Video::factory()->paid(500.00)->create();
        Coupon::factory()->percent(100)->create(['code' => 'FREE100']);

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video), ['coupon_code' => 'FREE100'])
            ->assertOk()
            ->assertJsonPath('amount', 100);      // Razorpay will not take ₹0

        $this->assertSame('1.00', Order::sole()->amount);
    }

    public function test_a_coupon_is_only_counted_once_the_payment_lands(): void
    {
        $video = Video::factory()->paid(1000.00)->create();
        $coupon = Coupon::factory()->percent(25)->create(['code' => 'DIWALI25']);

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video), ['coupon_code' => 'DIWALI25'])
            ->assertOk();

        // Abandoned checkout: nothing used yet.
        $this->assertSame(0, $coupon->fresh()->used_count);

        $this->useRealGateway();

        $this->actingAs($this->user)->post(route('payment.success'), [
            'razorpay_order_id' => 'order_ABC123',
            'razorpay_payment_id' => 'pay_XYZ789',
            'razorpay_signature' => hash_hmac('sha256', 'order_ABC123|pay_XYZ789', 'test_secret'),
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_a_replayed_callback_does_not_count_the_coupon_twice(): void
    {
        $video = Video::factory()->paid(1000.00)->create();
        $coupon = Coupon::factory()->percent(25)->create(['code' => 'DIWALI25']);

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video), ['coupon_code' => 'DIWALI25']);

        $this->useRealGateway();

        $payload = [
            'razorpay_order_id' => 'order_ABC123',
            'razorpay_payment_id' => 'pay_XYZ789',
            'razorpay_signature' => hash_hmac('sha256', 'order_ABC123|pay_XYZ789', 'test_secret'),
        ];

        $this->actingAs($this->user)->post(route('payment.success'), $payload);
        $this->actingAs($this->user)->post(route('payment.success'), $payload);

        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_the_discounted_amount_is_what_the_webhook_checks_against(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);

        $video = Video::factory()->paid(1000.00)->create();
        Coupon::factory()->percent(25)->create(['code' => 'DIWALI25']);

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video), ['coupon_code' => 'DIWALI25']);

        $this->useRealGateway();

        $body = json_encode([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_XYZ789',
                'order_id' => 'order_ABC123',
                'amount' => 75000,          // the discounted total, not ₹1000
            ]]],
        ]);

        $this->call('POST', route('razorpay.webhook'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'whsec_test'),
        ], content: $body)->assertOk()->assertJsonPath('message', 'Access granted.');

        $this->assertTrue($this->user->fresh()->hasPurchased($video));
    }

    public function test_coupon_model_rules(): void
    {
        $this->assertSame('DIWALI25', Coupon::normaliseCode('  diwali25 '));

        $live = Coupon::factory()->percent(40)->create();
        $this->assertTrue($live->isUsable());
        $this->assertSame(200.0, $live->discountOn(500.00));

        $this->assertFalse(Coupon::factory()->expired()->create()->isUsable());
        $this->assertFalse(Coupon::factory()->exhausted()->create()->isUsable());

        $this->assertSame(1, Coupon::usable()->count());
    }
}
