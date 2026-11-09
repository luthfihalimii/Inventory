<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'asset_code' => 'AST-'.fake()->unique()->numerify('######'),
            'serial_number' => Str::upper(fake()->unique()->bothify('SN-########')),
            'qr_token' => Str::random(40),
            'condition' => 'good',
            'status' => 'available',
        ];
    }
}
