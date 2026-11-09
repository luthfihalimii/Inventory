<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\DamageReport;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DamageReport>
 */
class DamageReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'asset_id' => null,
            'type' => 'damage',
            'quantity' => 1,
            'description' => fake()->sentence(),
            'status' => 'open',
            'reported_by_user_id' => User::factory(),
            'reported_at' => now(),
        ];
    }

    public function forAsset(Asset $asset): static
    {
        return $this->state(fn () => [
            'item_id' => $asset->item_id,
            'asset_id' => $asset->id,
            'quantity' => 1,
        ]);
    }

    public function resolved(string $resolution = 'repaired'): static
    {
        return $this->state(fn () => [
            'status' => 'resolved',
            'resolution' => $resolution,
            'resolved_at' => now(),
        ]);
    }
}
