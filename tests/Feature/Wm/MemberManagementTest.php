<?php

namespace Tests\Feature\Wm;

use App\Models\AuditLog;
use App\Models\Division;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private function wm(?Member $member = null): User
    {
        $user = User::factory()->create($member ? ['member_id' => $member->id] : []);
        $user->roles()->attach(Role::factory()->create(['name' => 'wm']));

        return $user;
    }

    public function test_non_wm_cannot_access_member_management(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'user']));

        $this->actingAs($user)->get(route('wm.members.index'))->assertForbidden();
    }

    public function test_wm_can_see_members_with_divisions(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($this->wm($member))
            ->get(route('wm.members.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('wm/members/Index')
                ->has('members.data', 1)
                ->where('members.data.0.nrp', $member->nrp));
    }

    public function test_wm_can_create_a_member(): void
    {
        $division = Division::factory()->create();

        $this->actingAs($this->wm())
            ->post(route('wm.members.store'), [
                'member_code' => 'MBR-9001',
                'name' => 'Anggota Baru',
                'nrp' => '2499999999',
                'division_id' => $division->id,
                'generation' => 2026,
                'position' => 'Reporter',
                'membership_status' => 'active',
                'borrowing_status' => 'eligible',
            ])
            ->assertRedirect();

        $member = Member::where('nrp', '2499999999')->firstOrFail();
        $this->assertSame('Anggota Baru', $member->name);
        $this->assertSame(1, AuditLog::where('action', 'member.created')->count());
    }

    public function test_two_digit_generation_is_normalized_to_a_year(): void
    {
        $division = Division::factory()->create();

        $this->actingAs($this->wm())
            ->post(route('wm.members.store'), [
                'member_code' => 'MBR-9003',
                'name' => 'Anggota Dua Digit',
                'nrp' => '2499999998',
                'division_id' => $division->id,
                'generation' => 21,
                'membership_status' => 'active',
                'borrowing_status' => 'eligible',
            ])
            ->assertRedirect();

        $this->assertSame(2021, Member::where('nrp', '2499999998')->firstOrFail()->generation);
    }

    public function test_member_nrp_must_be_unique(): void
    {
        $existing = Member::factory()->create();
        $division = Division::factory()->create();

        $this->actingAs($this->wm())
            ->post(route('wm.members.store'), [
                'member_code' => 'MBR-9002',
                'name' => 'Duplikat',
                'nrp' => $existing->nrp,
                'division_id' => $division->id,
                'membership_status' => 'active',
                'borrowing_status' => 'eligible',
            ])
            ->assertSessionHasErrors('nrp');
    }

    public function test_wm_can_update_member_status(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($this->wm())
            ->put(route('wm.members.update', $member), [
                'member_code' => $member->member_code,
                'name' => 'Nama Baru',
                'nrp' => $member->nrp,
                'division_id' => $member->division_id,
                'membership_status' => 'inactive',
                'borrowing_status' => 'eligible',
            ])
            ->assertRedirect();

        $member->refresh();
        $this->assertSame('Nama Baru', $member->name);
        $this->assertSame('inactive', $member->membership_status);
        $this->assertSame(1, AuditLog::where('action', 'member.updated')->count());
    }

    public function test_members_cannot_be_hard_deleted(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($this->wm())
            ->delete('/wm/members/'.$member->id)
            ->assertStatus(405);

        $this->assertNotNull($member->fresh());
    }
}
