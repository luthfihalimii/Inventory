<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Item;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_belongs_to_category_and_location_and_has_assets(): void
    {
        $item = Item::factory()->create();
        Asset::factory()->count(2)->for($item)->create();

        $this->assertTrue($item->category->exists);
        $this->assertTrue($item->location->exists);
        $this->assertCount(2, $item->assets);
    }

    public function test_asset_code_is_unique(): void
    {
        $asset = Asset::factory()->create();

        $this->expectException(QueryException::class);

        Asset::factory()->create(['asset_code' => $asset->asset_code]);
    }

    public function test_asset_qr_token_is_unique(): void
    {
        $asset = Asset::factory()->create();

        $this->expectException(QueryException::class);

        Asset::factory()->create(['qr_token' => $asset->qr_token]);
    }

    public function test_quantity_tracked_item_without_quantity_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        Item::factory()->create([
            'tracking_method' => 'quantity',
            'quantity_total' => null,
        ]);
    }

    public function test_serialized_item_with_quantity_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        Item::factory()->create([
            'tracking_method' => 'serialized',
            'quantity_total' => 3,
        ]);
    }

    public function test_quantity_tracked_item_keeps_quantity(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 12]);

        $this->assertSame(12, $item->quantity_total);
    }
}
