<?php

namespace Database\Factories;

use App\Models\BorrowRequest;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BorrowRequest>
 */
class BorrowRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference_no' => null,
            'member_id' => Member::factory(),
            'created_by_user_id' => User::factory(),
            'category' => 'scheduled',
            'pickup_at' => now()->addDays(3),
            'return_at' => now()->addDays(5),
            'usage_location' => fake()->city(),
            'purpose' => null,
            'notes' => null,
            'status' => 'pending_approval',
            'submitted_at' => now(),
        ];
    }

    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'urgent',
            'purpose' => fake()->sentence(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }
}
