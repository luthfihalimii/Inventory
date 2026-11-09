<?php

namespace Database\Factories;

use App\Models\SopDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SopDocument>
 */
class SopDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'version' => fake()->unique()->numberBetween(1, 999),
            'title' => 'SOP Peminjaman',
            'content' => fake()->paragraph(),
            'effective_at' => now(),
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
