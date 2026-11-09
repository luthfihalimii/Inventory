<?php

namespace Tests\Feature\Wm;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private function wm(?Member $member = null): User
    {
        $user = User::factory()->create($member ? ['member_id' => $member->id] : []);
        $user->roles()->attach(Role::factory()->create(['name' => 'wm']));

        return $user;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => $role]));

        return $user;
    }

    public function test_non_wm_cannot_access_account_management(): void
    {
        $this->actingAs($this->userWithRole('admin_korlab'))
            ->get(route('wm.accounts.index'))
            ->assertForbidden();
    }

    public function test_wm_can_list_accounts(): void
    {
        $wm = $this->wm();

        $this->actingAs($wm)
            ->get(route('wm.accounts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('wm/accounts/Index')->has('accounts.data'));
    }

    public function test_wm_can_create_an_account(): void
    {
        $member = Member::factory()->create();

        $response = $this->actingAs($this->wm())
            ->post(route('wm.accounts.store'), [
                'username' => 'korlab2',
                'member_id' => $member->id,
                'role' => 'admin_korlab',
                'password' => 'new-password-123',
            ]);

        $response->assertRedirect();

        $account = User::where('username', 'korlab2')->firstOrFail();

        $this->assertSame($member->id, $account->member_id);
        $this->assertTrue($account->must_change_password);
        $this->assertTrue($account->hasRole('admin_korlab'));
        $this->assertTrue(Hash::check('new-password-123', $account->password));
        $this->assertSame(1, AuditLog::where('action', 'account.created')->count());
    }

    public function test_username_must_be_unique(): void
    {
        $existing = User::factory()->create();

        $this->actingAs($this->wm())
            ->post(route('wm.accounts.store'), [
                'username' => $existing->username,
                'member_id' => Member::factory()->create()->id,
                'role' => 'user',
                'password' => 'new-password-123',
            ])
            ->assertSessionHasErrors('username');
    }

    public function test_wm_can_change_an_account_role(): void
    {
        $target = $this->userWithRole('user');

        $this->actingAs($this->wm())
            ->put(route('wm.accounts.roles.update', $target), ['role' => 'admin_korlab'])
            ->assertRedirect();

        $target->refresh();
        $this->assertTrue($target->hasRole('admin_korlab'));
        $this->assertFalse($target->hasRole('user'));
        $this->assertSame(1, AuditLog::where('action', 'account.roles_updated')->count());
    }

    public function test_wm_can_reset_an_account_password(): void
    {
        $target = $this->userWithRole('user');

        $this->actingAs($this->wm())
            ->put(route('wm.accounts.password.update', $target), ['password' => 'reset-password-123'])
            ->assertRedirect();

        $target->refresh();
        $this->assertTrue(Hash::check('reset-password-123', $target->password));
        $this->assertTrue($target->must_change_password);
        $this->assertSame(1, AuditLog::where('action', 'account.password_reset')->count());
    }

    public function test_wm_cannot_deactivate_own_account(): void
    {
        $wm = $this->wm();

        $this->actingAs($wm)
            ->put(route('wm.accounts.status.update', $wm), ['status' => 'inactive'])
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $wm->fresh()->status);
    }

    public function test_wm_cannot_remove_the_last_wm_role(): void
    {
        $wm = $this->wm();

        $this->actingAs($wm)
            ->put(route('wm.accounts.roles.update', $wm), ['role' => 'user'])
            ->assertSessionHasErrors('role');

        $this->assertTrue($wm->fresh()->hasRole('wm'));
    }

    public function test_wm_can_deactivate_another_account(): void
    {
        $target = $this->userWithRole('user');

        $this->actingAs($this->wm())
            ->put(route('wm.accounts.status.update', $target), ['status' => 'inactive'])
            ->assertRedirect();

        $this->assertSame('inactive', $target->fresh()->status);
        $this->assertSame(1, AuditLog::where('action', 'account.status_updated')->count());
    }
}
