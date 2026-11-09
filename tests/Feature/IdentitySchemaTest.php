<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentitySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_belongs_to_member_and_member_belongs_to_division(): void
    {
        $division = Division::factory()->create();
        $member = Member::factory()->for($division)->create();
        $user = User::factory()->for($member)->create();

        $this->assertTrue($user->member->is($member));
        $this->assertTrue($member->division->is($division));
        $this->assertTrue($member->users->contains($user));
    }

    public function test_user_can_have_multiple_roles(): void
    {
        $user = User::factory()->create();
        $roles = Role::factory()->createMany([['name' => 'user'], ['name' => 'admin_korlab']]);

        $user->roles()->attach($roles);

        $this->assertEqualsCanonicalizing(
            ['user', 'admin_korlab'],
            $user->fresh()->roles->pluck('name')->all(),
        );
    }

    public function test_member_nrp_is_unique(): void
    {
        $member = Member::factory()->create();

        $this->expectException(QueryException::class);

        Member::factory()->create(['nrp' => $member->nrp]);
    }

    public function test_username_is_unique(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        User::factory()->create(['username' => $user->username]);
    }

    public function test_new_user_is_active_and_member_is_eligible(): void
    {
        $user = User::factory()->create();

        $this->assertSame('active', $user->status);
        $this->assertFalse($user->must_change_password);
        $this->assertSame('active', $user->member->membership_status);
        $this->assertSame('eligible', $user->member->borrowing_status);
    }
}
