<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_cannot_see_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_reports_the_headline_statistics(): void
    {
        $admin = User::factory()->admin()->create();
        $buyers = User::factory()->count(3)->create();
        $video = Video::factory()->paid(250.00)->create();

        Order::factory()->successful()->for($buyers[0])->for($video)->create(['amount' => 250.00]);
        Order::factory()->successful()->for($buyers[1])->for($video)->create(['amount' => 150.50]);
        Order::factory()->failed()->for($buyers[2])->for($video)->create(['amount' => 999.00]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertViewHas('totalUsers', 3)      // administrators are excluded
            ->assertViewHas('totalVideos', 1)
            ->assertViewHas('totalOrders', 3)     // every status counts
            ->assertViewHas('totalRevenue', 400.50);

        // Only the paid orders show up in the recent list.
        $this->assertCount(2, $response->viewData('recentOrders'));
    }

    public function test_revenue_is_zero_without_successful_orders(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->failed()->create(['amount' => 500.00]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('totalRevenue', 0.0);
    }
}
