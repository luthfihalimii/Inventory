<?php

namespace Tests\Feature\Wm;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    private function wm(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'wm']));

        return $user;
    }

    public function test_non_wm_cannot_view_the_audit_log(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'user']));

        $this->actingAs($user)->get(route('wm.audit.index'))->assertForbidden();
    }

    public function test_wm_can_view_audit_entries(): void
    {
        AuditLog::create(['action' => 'member.created', 'created_at' => now()]);

        $this->actingAs($this->wm())
            ->get(route('wm.audit.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('wm/audit/Index')
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'member.created'));
    }

    public function test_audit_entries_can_be_filtered_by_action(): void
    {
        AuditLog::create(['action' => 'member.created', 'created_at' => now()]);
        AuditLog::create(['action' => 'auth.login', 'created_at' => now()]);

        $this->actingAs($this->wm())
            ->get(route('wm.audit.index', ['action' => 'auth']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'auth.login'));
    }
}
