<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_regular_users_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admins_can_reach_the_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_role_defaults_to_user_and_is_cast_to_enum(): void
    {
        $user = User::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'password',
        ]);

        $this->assertSame(UserRole::User, $user->fresh()->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_is_admin_reflects_the_admin_role(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->isAdmin());
    }
}
