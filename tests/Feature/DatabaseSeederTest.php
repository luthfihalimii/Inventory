<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Division;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_baseline_seed_data_is_created(): void
    {
        $this->seed();

        $this->assertEqualsCanonicalizing(
            ['user', 'admin_korlab', 'wm'],
            Role::pluck('name')->all(),
        );
        $this->assertSame(3, Division::count());
        $this->assertSame(3, Category::count());
        $this->assertSame(2, Location::count());

        $expectations = [
            'anggota' => 'user',
            'korlab' => 'admin_korlab',
            'wm' => 'wm',
        ];

        foreach ($expectations as $username => $role) {
            $user = User::where('username', $username)->firstOrFail();

            $this->assertNotNull($user->member);
            $this->assertNotNull($user->member->division);
            $this->assertSame([$role], $user->roles->pluck('name')->all());
        }
    }

    public function test_seeders_can_run_twice_without_duplicates(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(3, Role::count());
        $this->assertSame(3, User::count());
    }
}
