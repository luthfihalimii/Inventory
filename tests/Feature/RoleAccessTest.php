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

    public function test_staff_cannot_access_member_borrowing_pages(): void
    {
        foreach (['admin_korlab', 'wm'] as $roleName) {
            $user = User::factory()->create();
            $user->roles()->attach(Role::factory()->create(['name' => $roleName]));

            $this->actingAs($user)->get(route('borrowing.index'))->assertForbidden();
            $this->actingAs($user)->get(route('borrowing.create'))->assertForbidden();
            $this->actingAs($user)->post(route('borrowing.store'))->assertForbidden();
        }
    }

    public function test_member_can_access_member_borrowing_pages(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'user']));

        $this->actingAs($user)->get(route('borrowing.index'))->assertOk();
    }

    public function test_wm_can_access_all_staff_areas(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'wm']));

        foreach ([
            'admin.approval.index',
            'admin.extensions.index',
            'admin.reports.index',
            'admin.checkout.index',
            'admin.returns.index',
            'admin.inventory.items.index',
            'admin.announcements.index',
        ] as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertOk();
        }
    }

    public function test_admin_korlab_cannot_access_wm_areas(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'admin_korlab']));

        $this->actingAs($user)->get(route('wm.members.index'))->assertForbidden();
    }
}
