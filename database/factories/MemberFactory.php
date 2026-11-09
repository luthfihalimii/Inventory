<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_code' => 'MBR-'.fake()->unique()->numerify('#####'),
            'name' => fake()->name(),
            'nrp' => fake()->unique()->numerify('##########'),
            'division_id' => Division::factory(),
            'generation' => fake()->numberBetween(2020, 2026),
            'position' => fake()->jobTitle(),
            'membership_status' => 'active',
            'borrowing_status' => 'eligible',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'membership_status' => 'inactive',
        ]);
    }

    public function borrowingBlocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'borrowing_status' => 'borrowing_blocked',
        ]);
    }
}
