<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Buy button is useless without Razorpay's checkout.js, and the script tag
 * shipped for weeks without ever reaching the page: the @push sat AFTER
 * </x-public-layout>, and a component renders its slot before its own view, so
 * @stack had already been emitted and the push was silently discarded.
 *
 * Nothing threw, nothing logged, and the button simply did nothing.
 */
class CheckoutScriptTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'checkout.razorpay.com/v1/checkout.js';

    public function test_a_buyer_who_still_needs_to_pay_gets_the_checkout_script(): void
    {
        $video = Video::factory()->paid(999.00)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('videos.show', $video))
            ->assertOk()
            ->assertSee('razorpayCheckout')
            ->assertSee(self::SCRIPT, false);
    }

    public function test_a_guest_does_not_get_it_because_they_cannot_buy_yet(): void
    {
        $video = Video::factory()->paid(999.00)->create();

        $this->get(route('videos.show', $video))
            ->assertOk()
            ->assertSee('Sign in to buy')
            ->assertDontSee(self::SCRIPT, false);
    }

    public function test_someone_who_already_owns_the_lesson_does_not_load_it(): void
    {
        $video = Video::factory()->paid(999.00)->create();
        $owner = User::factory()->create();

        Order::factory()->for($owner)->for($video)->create(['status' => OrderStatus::Success]);
        $owner->purchasedVideos()->attach($video);

        $this->actingAs($owner)
            ->get(route('videos.show', $video))
            ->assertOk()
            ->assertDontSee(self::SCRIPT, false);
    }

    public function test_a_free_lesson_never_loads_a_payment_library(): void
    {
        $video = Video::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('videos.show', $video))
            ->assertOk()
            ->assertDontSee(self::SCRIPT, false);
    }

    public function test_payment_success_explains_itself_rather_than_throwing_when_keys_are_blank(): void
    {
        // Blank keys are the documented default in .env, and verifyPayment()
        // throws rather than returning false, so this used to be a white 500
        // page for an ordinary learner.
        config(['services.razorpay.key' => '', 'services.razorpay.secret' => '']);

        $user = User::factory()->create();
        $video = Video::factory()->paid(999.00)->create();

        $order = Order::factory()->for($user)->for($video)->create([
            'razorpay_order_id' => 'order_NOKEYS',
            'status' => OrderStatus::Pending,
        ]);

        $this->actingAs($user)
            ->post(route('payment.success'), [
                'razorpay_order_id' => $order->razorpay_order_id,
                'razorpay_payment_id' => 'pay_ANYTHING',
                'razorpay_signature' => 'nonsense',
            ])
            ->assertRedirect(route('videos.show', $video))
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'not switched on'));

        // And nothing was granted.
        $this->assertFalse($user->fresh()->hasPurchased($video));
    }
}
