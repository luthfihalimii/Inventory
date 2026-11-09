<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'ITM-'.fake()->unique()->numerify('#####'),
            'name' => fake()->words(3, true),
            'category_id' => Category::factory(),
            'tracking_method' => 'serialized',
            'quantity_total' => null,
            'price' => fake()->optional()->randomFloat(2, 10000, 5000000),
            'acquired_at' => fake()->optional()->dateTimeBetween('-5 years'),
            'status' => 'active',
            'location_id' => Location::factory(),
            'qr_token' => Str::random(40),
        ];
    }

    public function quantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'tracking_method' => 'quantity',
            'quantity_total' => fake()->numberBetween(1, 50),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
