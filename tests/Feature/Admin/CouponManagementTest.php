<?php

namespace Tests\Feature\Admin;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'code' => 'DIWALI25',
            'discount_percent' => '25',
            'max_uses' => '100',
            'expires_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_regular_users_cannot_manage_coupons(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.coupons.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.coupons.store'), $this->payload())->assertForbidden();

        $this->assertDatabaseEmpty('coupons');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('admin.coupons.index'))->assertRedirect(route('login'));
    }

    public function test_a_coupon_is_created_with_an_upper_cased_code(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.coupons.store'), $this->payload(['code' => ' diwali25 ']))
            ->assertRedirect(route('admin.coupons.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('coupons', [
            'code' => 'DIWALI25',
            'discount_percent' => 25,
            'max_uses' => 100,
            'is_active' => true,
            'used_count' => 0,
        ]);
    }

    public function test_an_unlimited_never_expiring_coupon_can_be_created(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.coupons.store'), $this->payload(['max_uses' => '', 'expires_at' => '']))
            ->assertSessionHasNoErrors();

        $coupon = Coupon::sole();
        $this->assertNull($coupon->max_uses);
        $this->assertNull($coupon->expires_at);
        $this->assertTrue($coupon->isUsable());
    }

    public function test_the_payload_is_validated(): void
    {
        $cases = [
            ['code' => ''],
            ['code' => 'HAS SPACES'],
            ['code' => 'bad*chars'],
            ['discount_percent' => '0'],
            ['discount_percent' => '101'],
            ['max_uses' => '0'],
            ['expires_at' => 'yesterday please'],
            ['expires_at' => now()->subDay()->format('Y-m-d\TH:i')],
        ];

        foreach ($cases as $bad) {
            $this->actingAs($this->admin)
                ->post(route('admin.coupons.store'), $this->payload($bad))
                ->assertSessionHasErrors(array_key_first($bad));
        }

        $this->assertDatabaseEmpty('coupons');
    }

    public function test_codes_must_be_unique(): void
    {
        Coupon::factory()->create(['code' => 'DIWALI25']);

        $this->actingAs($this->admin)
            ->post(route('admin.coupons.store'), $this->payload())
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Coupon::count());
    }

    public function test_a_coupon_can_be_edited_and_keeps_its_own_code(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'DIWALI25', 'discount_percent' => 25]);

        $this->actingAs($this->admin)
            ->put(route('admin.coupons.update', $coupon), $this->payload(['discount_percent' => '40']))
            ->assertRedirect(route('admin.coupons.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(40, $coupon->fresh()->discount_percent);
    }

    public function test_an_expired_coupon_can_still_be_edited(): void
    {
        $coupon = Coupon::factory()->expired()->create(['code' => 'OLD']);

        $this->actingAs($this->admin)
            ->put(route('admin.coupons.update', $coupon), $this->payload([
                'code' => 'OLD',
                'expires_at' => now()->subDay()->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_toggling_pauses_and_resumes_a_coupon(): void
    {
        $coupon = Coupon::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)
            ->from(route('admin.coupons.index'))
            ->patch(route('admin.coupons.toggle', $coupon))
            ->assertRedirect(route('admin.coupons.index'))
            ->assertSessionHas('success');

        $this->assertFalse($coupon->fresh()->is_active);

        $this->actingAs($this->admin)->patch(route('admin.coupons.toggle', $coupon));

        $this->assertTrue($coupon->fresh()->is_active);
    }

    public function test_a_paused_coupon_is_refused_at_checkout(): void
    {
        $coupon = Coupon::factory()->paused()->create(['code' => 'PAUSED']);

        $this->assertFalse($coupon->isUsable());
        $this->assertSame('Paused', $coupon->statusLabel());
        $this->assertSame(0, Coupon::usable()->count());
    }

    public function test_status_labels_explain_why_a_coupon_is_unusable(): void
    {
        $this->assertSame('Live', Coupon::factory()->create()->statusLabel());
        $this->assertSame('Paused', Coupon::factory()->paused()->create()->statusLabel());
        $this->assertSame('Expired', Coupon::factory()->expired()->create()->statusLabel());
        $this->assertSame('Used up', Coupon::factory()->exhausted()->create()->statusLabel());
    }

    public function test_an_unused_coupon_can_be_deleted(): void
    {
        $coupon = Coupon::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.coupons.destroy', $coupon))
            ->assertSessionHas('success');

        $this->assertDatabaseEmpty('coupons');
    }

    public function test_a_coupon_used_on_an_order_cannot_be_deleted(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'USED']);
        Order::factory()->create(['coupon_id' => $coupon->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.coupons.destroy', $coupon))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);
    }

    public function test_the_list_reports_usage_and_revenue(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'DIWALI25', 'used_count' => 2]);

        Order::factory()->successful()->create([
            'coupon_id' => $coupon->id, 'amount' => 750.00, 'discount_amount' => 250.00,
        ]);
        Order::factory()->successful()->create([
            'coupon_id' => $coupon->id, 'amount' => 1500.00, 'discount_amount' => 500.00,
        ]);
        // An abandoned attempt must not inflate the figures.
        Order::factory()->create([
            'coupon_id' => $coupon->id, 'amount' => 9999.00, 'discount_amount' => 9999.00,
        ]);

        $row = $this->actingAs($this->admin)
            ->get(route('admin.coupons.index'))
            ->assertOk()
            ->assertSee('DIWALI25')
            ->viewData('coupons')
            ->first();

        $this->assertSame(2, $row->paid_orders);
        $this->assertSame(750.0, (float) $row->discount_given);
        $this->assertSame(2250.0, (float) $row->revenue);
    }

    public function test_the_list_can_be_searched(): void
    {
        Coupon::factory()->create(['code' => 'DIWALI25']);
        Coupon::factory()->create(['code' => 'HOLI10']);

        $this->actingAs($this->admin)
            ->get(route('admin.coupons.index', ['search' => 'diwali']))
            ->assertOk()
            ->assertSee('DIWALI25')
            ->assertDontSee('HOLI10');
    }

    public function test_the_admin_nav_links_to_coupons(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.coupons.index'));
    }
}
