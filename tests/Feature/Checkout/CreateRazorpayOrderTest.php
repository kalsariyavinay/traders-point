<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use App\Services\RazorpayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class CreateRazorpayOrderTest extends TestCase
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
    }

    private function gatewayReturning(string $razorpayOrderId = 'order_TESTxyz123'): void
    {
        $this->mock(RazorpayGateway::class, function (MockInterface $mock) use ($razorpayOrderId) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('key')->andReturn('rzp_test_key');
            $mock->shouldReceive('currency')->andReturn('INR');
            $mock->shouldReceive('createOrder')->once()->andReturn($razorpayOrderId);
        });
    }

    public function test_a_guest_cannot_start_a_checkout(): void
    {
        $video = Video::factory()->paid()->create();

        $this->postJson(route('razorpay.order', $video))->assertUnauthorized();

        $this->assertDatabaseEmpty('orders');
    }

    public function test_it_creates_a_pending_order_and_returns_the_checkout_options(): void
    {
        $this->gatewayReturning('order_ABC123');
        $video = Video::factory()->paid(1499.00)->create(['title' => 'Options Greeks Explained']);

        $response = $this->actingAs($this->user)->postJson(route('razorpay.order', $video));

        $response->assertOk()->assertJson([
            'key' => 'rzp_test_key',
            'order_id' => 'order_ABC123',
            'amount' => 149900,          // rupees converted to paise
            'currency' => 'INR',
            'name' => 'Traders Point Academy',
            'description' => 'Options Greeks Explained',
            'prefill' => [
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
        ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'video_id' => $video->id,
            'razorpay_order_id' => 'order_ABC123',
            'amount' => '1499.00',
            'status' => OrderStatus::Pending->value,
        ]);

        // Nothing is unlocked until the payment comes back verified.
        $this->assertDatabaseEmpty('video_user');
    }

    public function test_the_amount_comes_from_the_video_not_the_request(): void
    {
        $this->gatewayReturning();
        $video = Video::factory()->paid(2000.00)->create();

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video), ['amount' => 1, 'price' => 1])
            ->assertOk()
            ->assertJson(['amount' => 200000]);

        $this->assertSame('2000.00', Order::sole()->amount);
    }

    public function test_a_second_attempt_reuses_the_open_order(): void
    {
        $video = Video::factory()->paid()->create();

        $this->mock(RazorpayGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('key')->andReturn('rzp_test_key');
            $mock->shouldReceive('currency')->andReturn('INR');
            $mock->shouldReceive('createOrder')->twice()
                ->andReturn('order_FIRST', 'order_SECOND');
        });

        $this->actingAs($this->user)->postJson(route('razorpay.order', $video))->assertOk();
        $this->actingAs($this->user)->postJson(route('razorpay.order', $video))->assertOk();

        $this->assertSame(1, Order::count());
        $this->assertSame('order_SECOND', Order::sole()->razorpay_order_id);
    }

    public function test_a_free_video_is_rejected(): void
    {
        $video = Video::factory()->create();

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video))
            ->assertStatus(422);

        $this->assertDatabaseEmpty('orders');
    }

    public function test_a_video_the_user_already_owns_is_rejected(): void
    {
        $video = Video::factory()->paid()->create();
        $this->user->purchasedVideos()->attach($video);

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video))
            ->assertStatus(409);

        $this->assertDatabaseEmpty('orders');
    }

    public function test_a_draft_cannot_be_bought(): void
    {
        $video = Video::factory()->paid()->draft()->create();

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video))
            ->assertNotFound();
    }

    public function test_missing_api_keys_are_reported_rather_than_crashing(): void
    {
        config(['services.razorpay.key' => null, 'services.razorpay.secret' => null]);
        $video = Video::factory()->paid()->create();

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video))
            ->assertStatus(503)
            ->assertJsonPath('message', 'Payments are not switched on yet. Please try again later.');

        $this->assertDatabaseEmpty('orders');
    }

    public function test_a_gateway_outage_leaves_the_order_without_a_razorpay_id(): void
    {
        $this->mock(RazorpayGateway::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('createOrder')->once()->andThrow(new RuntimeException('network down'));
        });

        $video = Video::factory()->paid()->create();

        $this->actingAs($this->user)
            ->postJson(route('razorpay.order', $video))
            ->assertStatus(502);

        $this->assertDatabaseHas('orders', [
            'video_id' => $video->id,
            'razorpay_order_id' => null,
            'status' => OrderStatus::Pending->value,
        ]);
        $this->assertDatabaseEmpty('video_user');
    }
}
