<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:wm'])->get('/test/wm-only', fn () => 'ok');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/test/wm-only')->assertRedirect(route('login'));
    }

    public function test_user_without_role_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/test/wm-only')->assertForbidden();
    }

    public function test_member_of_allowed_role_passes(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'wm']));

        $this->actingAs($user)->get('/test/wm-only')->assertOk();
    }

    public function test_multiple_allowed_roles_are_supported(): void
    {
        Route::middleware(['web', 'auth', 'role:admin_korlab,wm'])->get('/test/staff-only', fn () => 'ok');

        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'admin_korlab']));

        $this->actingAs($user)->get('/test/staff-only')->assertOk();
    }

    public function test_has_role_helpers(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'admin_korlab']));

        $this->assertTrue($user->hasRole('admin_korlab'));
        $this->assertFalse($user->hasRole('wm'));
        $this->assertTrue($user->hasAnyRole(['wm', 'admin_korlab']));
        $this->assertFalse($user->hasAnyRole(['wm', 'user']));
    }
}
