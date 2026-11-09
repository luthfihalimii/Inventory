<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\DamageReport;
use App\Models\Item;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_quantity_availability_subtracts_only_open_reports(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 10]);

        DamageReport::factory()->create(['item_id' => $item->id, 'quantity' => 3, 'status' => 'open']);
        DamageReport::factory()->resolved()->create(['item_id' => $item->id, 'quantity' => 2]);

        $summary = app(AvailabilityService::class)->summary($item);

        $this->assertSame(10, $summary['total']);
        $this->assertSame(7, $summary['available']);
        $this->assertSame(3, $summary['damaged']);
    }

    public function test_serialized_availability_counts_each_asset_status(): void
    {
        $item = Item::factory()->create();

        Asset::factory()->for($item)->create(['status' => 'available']);
        Asset::factory()->for($item)->create(['status' => 'available']);
        Asset::factory()->for($item)->create(['status' => 'borrowed']);
        Asset::factory()->for($item)->create(['status' => 'damaged']);
        Asset::factory()->for($item)->create(['status' => 'lost']);

        $summary = app(AvailabilityService::class)->summary($item);

        $this->assertSame(5, $summary['total']);
        $this->assertSame(2, $summary['available']);
        $this->assertSame(1, $summary['borrowed']);
        $this->assertSame(1, $summary['damaged']);
        $this->assertSame(1, $summary['lost']);
    }

    public function test_inactive_item_is_never_available(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5, 'status' => 'inactive']);

        $this->assertFalse(app(AvailabilityService::class)->isAvailable($item));
    }

    public function test_is_available_respects_open_reports(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 2]);
        DamageReport::factory()->create(['item_id' => $item->id, 'quantity' => 1]);

        $service = app(AvailabilityService::class);

        $this->assertTrue($service->isAvailable($item, 1));
        $this->assertFalse($service->isAvailable($item, 2));
    }
}
