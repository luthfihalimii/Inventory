<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'username' => null,
            'password' => 'password',
            'status' => 'ACTIVE',
            'remember_token' => Str::random(10),
        ];
    }

    public function manager(string $username): static
    {
        return $this->state(fn () => ['username' => $username]);
    }
}
