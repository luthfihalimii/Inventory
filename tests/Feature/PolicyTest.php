<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_wm_can_manage_members_and_accounts(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'user']));

        $this->assertFalse($user->can('manage', Member::class));
        $this->assertFalse($user->can('manage', User::class));

        $user->roles()->attach(Role::factory()->create(['name' => 'wm']));

        $this->assertTrue($user->fresh()->can('manage', Member::class));
        $this->assertTrue($user->fresh()->can('manage', User::class));
    }
}
