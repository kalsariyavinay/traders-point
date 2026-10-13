<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The webhook is unauthenticated and CSRF-exempt, so its signature is the only
 * thing protecting someone else's purchase. These run against the real gateway
 * object -- webhook verification is pure HMAC over the raw body, no network.
 */
class RazorpayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test';

    private User $user;

    private Video $video;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'test_secret',
            'services.razorpay.webhook_secret' => self::WEBHOOK_SECRET,
        ]);

        $this->user = User::factory()->create();
        $this->video = Video::factory()->paid(1499.00)->create();

        $this->order = Order::factory()->for($this->user)->for($this->video)->create([
            'razorpay_order_id' => 'order_ABC123',
            'amount' => 1499.00,
            'status' => OrderStatus::Pending,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function captured(array $overrides = []): array
    {
        return [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_XYZ789',
                        'order_id' => 'order_ABC123',
                        'amount' => 149900,
                        'currency' => 'INR',
                        'status' => 'captured',
                        ...$overrides,
                    ],
                ],
            ],
        ];
    }

    /**
     * Razorpay signs the raw request body, so the test must sign the exact
     * bytes it sends.
     *
     * @param  array<string, mixed>  $body
     */
    private function send(array $body, ?string $secret = self::WEBHOOK_SECRET, ?string $signature = null)
    {
        $raw = json_encode($body);

        return $this->call(
            method: 'POST',
            uri: route('razorpay.webhook'),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $raw, (string) $secret),
            ],
            content: $raw,
        );
    }

    public function test_a_captured_payment_unlocks_the_video(): void
    {
        $this->send($this->captured())->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatus::Success->value,
            'razorpay_payment_id' => 'pay_XYZ789',
        ]);

        $this->assertDatabaseHas('video_user', [
            'user_id' => $this->user->id,
            'video_id' => $this->video->id,
        ]);
    }

    public function test_it_works_without_a_session_or_csrf_token(): void
    {
        // No actingAs, no token: the signature is the whole authentication.
        $this->send($this->captured())->assertOk();

        $this->assertTrue($this->user->fresh()->hasPurchased($this->video));
    }

    public function test_a_forged_signature_grants_nothing(): void
    {
        $this->send($this->captured(), secret: 'attacker_guess')
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid signature.');

        $this->assertDatabaseEmpty('video_user');
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatus::Pending->value,
        ]);
    }

    public function test_a_missing_signature_header_is_rejected(): void
    {
        $raw = json_encode($this->captured());

        $this->call('POST', route('razorpay.webhook'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: $raw)->assertStatus(400);

        $this->assertDatabaseEmpty('video_user');
    }

    public function test_a_tampered_body_breaks_the_signature(): void
    {
        $signed = hash_hmac('sha256', json_encode($this->captured()), self::WEBHOOK_SECRET);

        // Same signature, different body: the amount has been inflated.
        $this->send($this->captured(['amount' => 1]), signature: $signed)->assertStatus(400);

        $this->assertDatabaseEmpty('video_user');
    }

    public function test_it_refuses_to_run_without_a_configured_secret(): void
    {
        config(['services.razorpay.webhook_secret' => null]);

        $this->send($this->captured())->assertStatus(503);

        $this->assertDatabaseEmpty('video_user');
    }

    public function test_other_events_are_acknowledged_but_ignored(): void
    {
        $body = $this->captured();
        $body['event'] = 'payment.authorized';

        $this->send($body)->assertOk()->assertJsonPath('message', 'Ignored event [payment.authorized].');

        $this->assertDatabaseEmpty('video_user');
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatus::Pending->value,
        ]);
    }

    public function test_an_unknown_order_is_acknowledged_so_razorpay_stops_retrying(): void
    {
        $this->send($this->captured(['order_id' => 'order_NOT_OURS']))
            ->assertOk()
            ->assertJsonPath('message', 'Order not found.');

        $this->assertDatabaseEmpty('video_user');
    }

    public function test_an_underpaid_capture_does_not_unlock_the_video(): void
    {
        $this->send($this->captured(['amount' => 100]))   // ₹1 against a ₹1499 order
            ->assertOk()
            ->assertJsonPath('message', 'Amount mismatch.');

        $this->assertDatabaseEmpty('video_user');
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatus::Pending->value,
        ]);
    }

    public function test_an_incomplete_payload_is_acknowledged(): void
    {
        $body = ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => []]]];

        $this->send($body)->assertOk()->assertJsonPath('message', 'Incomplete payload.');

        $this->assertDatabaseEmpty('video_user');
    }

    public function test_a_retried_webhook_does_not_grant_twice(): void
    {
        $this->send($this->captured())->assertOk();
        $this->send($this->captured())->assertOk()->assertJsonPath('message', 'Already fulfilled.');

        $this->assertDatabaseCount('video_user', 1);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_the_webhook_is_harmless_after_the_browser_already_confirmed(): void
    {
        // The buyer's browser got there first.
        $this->actingAs($this->user)->post(route('payment.success'), [
            'razorpay_order_id' => 'order_ABC123',
            'razorpay_payment_id' => 'pay_XYZ789',
            'razorpay_signature' => hash_hmac('sha256', 'order_ABC123|pay_XYZ789', 'test_secret'),
        ])->assertRedirect(route('dashboard'));

        $this->send($this->captured())->assertOk()->assertJsonPath('message', 'Already fulfilled.');

        $this->assertDatabaseCount('video_user', 1);
    }

    public function test_the_webhook_rescues_a_payment_the_browser_never_reported(): void
    {
        // Nothing came back from the browser -- the order is still pending.
        $this->assertFalse($this->user->hasPurchased($this->video));

        $this->send($this->captured())->assertOk()->assertJsonPath('message', 'Access granted.');

        $this->assertTrue($this->user->fresh()->hasPurchased($this->video));
    }
}
