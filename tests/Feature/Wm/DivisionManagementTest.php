<?php

namespace Tests\Feature\Wm;

use App\Models\AuditLog;
use App\Models\Division;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function wm(?Member $member = null): User
    {
        $user = User::factory()->create($member ? ['member_id' => $member->id] : []);
        $user->roles()->attach(Role::factory()->create(['name' => 'wm']));

        return $user;
    }

    public function test_wm_can_list_divisions(): void
    {
        $division = Division::factory()->create();

        $this->actingAs($this->wm(Member::factory()->create(['division_id' => $division->id])))
            ->get(route('wm.divisions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('wm/divisions/Index')->has('divisions.data', 1));
    }

    public function test_wm_can_create_and_update_a_division(): void
    {
        $wm = $this->wm();

        $this->actingAs($wm)->post(route('wm.divisions.store'), [
            'name' => 'Desain',
            'code' => 'DSN',
        ])->assertRedirect();

        $division = Division::where('code', 'DSN')->firstOrFail();

        $this->actingAs($wm)->put(route('wm.divisions.update', $division), [
            'name' => 'Desain Grafis',
            'code' => 'DSN',
        ])->assertRedirect();

        $this->assertSame('Desain Grafis', $division->fresh()->name);
        $this->assertSame(1, AuditLog::where('action', 'division.created')->count());
        $this->assertSame(1, AuditLog::where('action', 'division.updated')->count());
    }

    public function test_division_code_must_be_unique(): void
    {
        $existing = Division::factory()->create();

        $this->actingAs($this->wm())
            ->post(route('wm.divisions.store'), [
                'name' => 'Lain',
                'code' => $existing->code,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_non_wm_cannot_manage_divisions(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'admin_korlab']));

        $this->actingAs($user)->post(route('wm.divisions.store'), [
            'name' => 'Desain',
            'code' => 'DSN',
        ])->assertForbidden();
    }
}
