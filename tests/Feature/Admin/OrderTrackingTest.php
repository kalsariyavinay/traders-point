<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_regular_users_cannot_see_orders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.index'))
            ->assertForbidden();
    }

    public function test_successful_orders_are_listed_by_default(): void
    {
        $video = Video::factory()->paid()->create(['title' => 'Options Greeks', 'slug' => 'options-greeks']);

        $paid = User::factory()->create(['name' => 'Asha Paid', 'email' => 'asha@example.com']);
        $unpaid = User::factory()->create(['name' => 'Bhavin Failed', 'email' => 'bhavin@example.com']);

        Order::factory()->successful()->for($paid)->for($video)->create(['amount' => 799.00]);
        Order::factory()->failed()->for($unpaid)->for($video)->create(['amount' => 799.00]);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.index'));

        $response->assertOk()
            ->assertViewHas('status', 'success')
            ->assertViewHas('revenue', 799.0)
            ->assertSee('Asha Paid')
            ->assertSee('asha@example.com')
            ->assertSee('Options Greeks')
            ->assertDontSee('Bhavin Failed');
    }

    public function test_the_status_filter_can_show_every_order(): void
    {
        Order::factory()->successful()->create();
        Order::factory()->failed()->create();
        Order::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['status' => 'all']));

        $response->assertOk();
        $this->assertSame(3, $response->viewData('orders')->total());
    }

    public function test_an_unknown_status_falls_back_to_successful(): void
    {
        Order::factory()->successful()->create();
        Order::factory()->failed()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['status' => 'bogus']));

        $response->assertOk()->assertViewHas('status', 'success');
        $this->assertSame(1, $response->viewData('orders')->total());
    }

    public function test_orders_can_be_searched_by_user_email_and_payment_id(): void
    {
        $wanted = User::factory()->create(['name' => 'Chirag Shah', 'email' => 'chirag@example.com']);
        $other = User::factory()->create(['name' => 'Divya Mehta', 'email' => 'divya@example.com']);

        $order = Order::factory()->successful()->for($wanted)->create();
        Order::factory()->successful()->for($other)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['search' => 'chirag@example.com']))
            ->assertOk()
            ->assertSee('Chirag Shah')
            ->assertDontSee('Divya Mehta');

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['search' => $order->razorpay_payment_id]))
            ->assertOk()
            ->assertSee('Chirag Shah')
            ->assertDontSee('Divya Mehta');
    }
}
