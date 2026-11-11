<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function sessionRow(User $user, string $id, ?int $lastActivity = null): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
            'payload' => '',
            'last_activity' => $lastActivity ?? now()->timestamp,
        ]);
    }

    public function test_security_page_lists_only_the_users_own_sessions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->sessionRow($user, 'session-own');
        $this->sessionRow($other, 'session-other');

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->has('sessions', 1)
                ->where('sessions.0.id', 'session-own')
                ->where('sessions.0.device', 'Chrome · Windows'));
    }

    public function test_user_can_revoke_one_of_their_other_sessions(): void
    {
        $user = User::factory()->create();
        $this->sessionRow($user, 'session-own');
        $this->sessionRow($user, 'session-lama', now()->subHour()->timestamp);

        $this->actingAs($user)
            ->delete(route('sessions.destroy', 'session-lama'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('sessions')->where('id', 'session-lama')->count());
        $this->assertSame(1, DB::table('sessions')->where('id', 'session-own')->count());
        $this->assertSame(1, AuditLog::where('action', 'session.revoked')->count());
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->sessionRow($other, 'session-other');

        $this->actingAs($user)
            ->delete(route('sessions.destroy', 'session-other'))
            ->assertNotFound();

        $this->assertSame(1, DB::table('sessions')->where('id', 'session-other')->count());
    }

    public function test_current_session_cannot_be_revoked_from_the_list(): void
    {
        $user = User::factory()->create();
        // Laravel only accepts 40-char alphanumeric session ids from the cookie.
        $currentId = str_repeat('c', 40);
        $this->sessionRow($user, $currentId);

        $this->withCookie(config('session.cookie'), $currentId)
            ->actingAs($user)
            ->delete(route('sessions.destroy', $currentId))
            ->assertSessionHasErrors('session');

        $this->assertSame(1, DB::table('sessions')->where('id', $currentId)->count());
    }

    public function test_revoke_other_sessions_keeps_the_current_one(): void
    {
        $user = User::factory()->create();
        $currentId = str_repeat('c', 40);
        $this->sessionRow($user, $currentId);
        $this->sessionRow($user, 'session-a', now()->subDay()->timestamp);
        $this->sessionRow($user, 'session-b', now()->subDays(2)->timestamp);

        $this->withCookie(config('session.cookie'), $currentId)
            ->actingAs($user)
            ->delete(route('sessions.destroy-others'))
            ->assertRedirect();

        $this->assertSame(1, DB::table('sessions')->count());
        $this->assertSame(1, DB::table('sessions')->where('id', $currentId)->count());
        $this->assertSame(1, AuditLog::where('action', 'session.revoked_others')->count());
    }

    public function test_changing_password_revokes_other_sessions_and_keeps_current(): void
    {
        $user = User::factory()->create();
        $currentId = str_repeat('c', 40);
        $this->sessionRow($user, $currentId);
        $this->sessionRow($user, 'session-lain', now()->subHour()->timestamp);

        $this->withCookie(config('session.cookie'), $currentId)
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('sessions')->count());
        $this->assertSame(1, DB::table('sessions')->where('id', $currentId)->count());
        $this->assertSame(1, AuditLog::where('action', 'account.password_changed')->count());
    }

    public function test_wm_password_reset_revokes_every_session_of_the_account(): void
    {
        $wm = User::factory()->create();
        $wm->roles()->attach(Role::firstOrCreate(['name' => 'wm']));

        $target = User::factory()->create();
        $this->sessionRow($target, 'target-a');
        $this->sessionRow($target, 'target-b');
        $this->sessionRow($wm, 'wm-session');

        $this->actingAs($wm)
            ->put(route('wm.accounts.password.update', $target), ['password' => 'reset-password-123'])
            ->assertRedirect();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('id', 'wm-session')->count());

        $log = AuditLog::where('action', 'account.password_reset')->firstOrFail();
        $this->assertSame(2, $log->metadata['sessions_revoked']);
    }
}
