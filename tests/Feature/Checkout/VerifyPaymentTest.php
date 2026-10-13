<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * These run against the real gateway object -- verifying a signature is pure
 * HMAC with the API secret, so no network and no mock is involved.
 */
class VerifyPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test_secret';

    private User $user;

    private Video $video;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => self::SECRET,
        ]);

        $this->user = User::factory()->create();
        $this->video = Video::factory()->paid(999.00)->create(['title' => 'Options Greeks Explained']);

        $this->order = Order::factory()->for($this->user)->for($this->video)->create([
            'razorpay_order_id' => 'order_ABC123',
            'amount' => 999.00,
            'status' => OrderStatus::Pending,
        ]);
    }

    /** Razorpay signs "{order_id}|{payment_id}" with the API secret. */
    private function signature(string $orderId, string $paymentId, string $secret = self::SECRET): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);
    }

    public function test_a_valid_signature_unlocks_the_video(): void
    {
        $response = $this->actingAs($this->user)->post(route('payment.success'), [
            'razorpay_order_id' => 'order_ABC123',
            'razorpay_payment_id' => 'pay_XYZ789',
            'razorpay_signature' => $this->signature('order_ABC123', 'pay_XYZ789'),
        ]);

        $response->assertRedirect(route('dashboard'))->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatus::Success->value,
            'razorpay_payment_id' => 'pay_XYZ789',
        ]);

        $this->assertDatabaseHas('video_user', [
            'user_id' => $this->user->id,
            'video_id' => $this->video->id,
        ]);

        $this->assertTrue($this->user->fresh()->hasPurchased($this->video));
    }

    public function test_a_forged_signature_grants_nothing(): void
    {
        $response = $this->actingAs($this->user)->post(route('payment.success'), [
            'razorpay_order_id' => 'order_ABC123',
            'razorpay_payment_id' => 'pay_XYZ789',
            'razorpay_signature' => $this->signature('order_ABC123', 'pay_XYZ789', 'attacker_guess'),
        ]);

        $response->assertRedirect(route('videos.show', $this->video))->assertSessionHas('error');

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatus::Failed->value,
        ]);
        $this->assertDatabaseEmpty('video_user');
        $this->assertFalse($this->user->fresh()->hasPurchased($this->video));
    }

    public function test_a_signature_for_a_different_payment_is_rejected(): void
    {
        // Correctly signed, but for another payment id -- replaying it must fail.
        $this->actingAs($this->user)->post(route('payment.success'), [
            'razorpay_order_id' => 'order_ABC123',
            'razorpay_payment_id' => 'pay_SOMETHING_ELSE',
            'razorpay_signature' => $this->signature('order_ABC123', 'pay_XYZ789'),
        ])->assertSessionHas('error');

        $this->assertDatabaseEmpty('video_user');
    }

    public function test_another_users_order_cannot_be_claimed(): void
    {
        $attacker = User::factory()->create();

        $response = $this->actingAs($attacker)->post(route('payment.success'), [
            'razorpay_order_id' => 'order_ABC123',
            'razorpay_payment_id' => 'pay_XYZ789',
            'razorpay_signature' => $this->signature('order_ABC123', 'pay_XYZ789'),
        ]);

        $response->assertRedirect(route('home'))->assertSessionHas('error');

        $this->assertDatabaseEmpty('video_user');
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatus::Pending->value,
        ]);
    }

    public function test_a_repeated_callback_does_not_double_grant(): void
    {
        $payload = [
            'razorpay_order_id' => 'order_ABC123',
            'razorpay_payment_id' => 'pay_XYZ789',
            'razorpay_signature' => $this->signature('order_ABC123', 'pay_XYZ789'),
        ];

        $this->actingAs($this->user)->post(route('payment.success'), $payload);
        $this->actingAs($this->user)->post(route('payment.success'), $payload)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('video_user', 1);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_an_unknown_order_is_turned_away(): void
    {
        $this->actingAs($this->user)->post(route('payment.success'), [
            'razorpay_order_id' => 'order_NOT_OURS',
            'razorpay_payment_id' => 'pay_XYZ789',
            'razorpay_signature' => $this->signature('order_NOT_OURS', 'pay_XYZ789'),
        ])->assertRedirect(route('home'))->assertSessionHas('error');

        $this->assertDatabaseEmpty('video_user');
    }

    public function test_the_callback_requires_all_three_fields(): void
    {
        $this->actingAs($this->user)
            ->post(route('payment.success'), ['razorpay_order_id' => 'order_ABC123'])
            ->assertSessionHasErrors(['razorpay_payment_id', 'razorpay_signature']);
    }

    public function test_a_guest_cannot_post_a_payment_result(): void
    {
        $this->post(route('payment.success'), [
            'razorpay_order_id' => 'order_ABC123',
            'razorpay_payment_id' => 'pay_XYZ789',
            'razorpay_signature' => $this->signature('order_ABC123', 'pay_XYZ789'),
        ])->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('video_user');
    }

    public function test_a_declined_payment_marks_the_order_failed(): void
    {
        $this->actingAs($this->user)
            ->from(route('videos.show', $this->video))
            ->post(route('payment.failed'), [
                'razorpay_order_id' => 'order_ABC123',
                'reason' => 'Card declined',
            ])
            ->assertRedirect(route('videos.show', $this->video))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatus::Failed->value,
        ]);
        $this->assertDatabaseEmpty('video_user');
    }

    public function test_a_paid_order_is_not_downgraded_by_a_late_failure_report(): void
    {
        $this->order->update(['status' => OrderStatus::Success]);

        $this->actingAs($this->user)
            ->from(route('videos.show', $this->video))
            ->post(route('payment.failed'), ['razorpay_order_id' => 'order_ABC123']);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatus::Success->value,
        ]);
    }
}
