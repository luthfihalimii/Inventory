<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\BorrowRequest;
use App\Models\DamageReport;
use App\Models\Item;
use App\Models\LoanItem;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\User;
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

    public function test_quantity_availability_for_period_subtracts_overlapping_reservations(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        $this->reserve($item, quantity: 2, start: now()->addDays(3), end: now()->addDays(5));

        $service = app(AvailabilityService::class);

        $this->assertSame(3, $service->availableForPeriod($item, now()->addDays(3), now()->addDays(5)));
        $this->assertSame(5, $service->availableForPeriod($item, now()->addDays(10), now()->addDays(12)));
    }

    public function test_serialized_availability_for_period_skips_reserved_units(): void
    {
        $item = Item::factory()->create();
        $first = Asset::factory()->for($item)->create(['status' => 'available']);
        Asset::factory()->for($item)->create(['status' => 'available']);

        $this->reserve($item, quantity: 1, start: now()->addDays(3), end: now()->addDays(5), asset: $first);

        $service = app(AvailabilityService::class);

        $this->assertSame(1, $service->availableForPeriod($item, now()->addDays(3), now()->addDays(5)));
        $this->assertSame(2, $service->availableForPeriod($item, now()->addDays(10), now()->addDays(12)));
    }

    public function test_summary_subtracts_outstanding_loans(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 10]);
        $this->loan($item, quantity: 4);

        $summary = app(AvailabilityService::class)->summary($item);

        $this->assertSame(4, $summary['borrowed']);
        $this->assertSame(6, $summary['available']);
    }

    public function test_quantity_availability_for_period_subtracts_overlapping_loans(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        $this->loan($item, quantity: 2, start: now()->subDay(), end: now()->addDays(2));

        $service = app(AvailabilityService::class);

        $this->assertSame(3, $service->availableForPeriod($item, now(), now()->addDay()));
        $this->assertSame(5, $service->availableForPeriod($item, now()->addDays(5), now()->addDays(7)));
    }

    public function test_serialized_unit_becomes_bookable_after_its_loan_ends(): void
    {
        $item = Item::factory()->create();
        $asset = Asset::factory()->for($item)->create(['status' => 'borrowed']);
        $this->loan($item, quantity: 1, start: now()->subDay(), end: now()->addDays(2), asset: $asset);

        $service = app(AvailabilityService::class);

        $this->assertSame(0, $service->availableForPeriod($item, now(), now()->addDay()));
        $this->assertSame(1, $service->availableForPeriod($item, now()->addDays(3), now()->addDays(5)));
    }

    private function loan(
        Item $item,
        int $quantity,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
        ?Asset $asset = null,
    ): void {
        $member = Member::factory()->create();
        $request = BorrowRequest::factory()->approved()->create([
            'member_id' => $member->id,
            'created_by_user_id' => User::factory()->create(['member_id' => $member->id])->id,
        ]);

        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => $request->id,
            'member_id' => $member->id,
            'status' => 'borrowed',
            'checked_out_at' => $start ?? now()->subDay(),
            'due_at' => $end ?? now()->addDays(2),
        ]);

        LoanItem::factory()->create([
            'loan_transaction_id' => $loan->id,
            'item_id' => $item->id,
            'asset_id' => $asset?->id,
            'quantity' => $asset ? 1 : $quantity,
        ]);
    }

    private function reserve(
        Item $item,
        int $quantity,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        ?Asset $asset = null,
    ): void {
        $member = Member::factory()->create();
        $request = BorrowRequest::factory()->create([
            'member_id' => $member->id,
            'created_by_user_id' => User::factory()->create(['member_id' => $member->id])->id,
        ]);
        $line = $request->items()->create(['item_id' => $item->id, 'quantity' => $quantity]);

        Reservation::factory()->create([
            'borrow_request_id' => $request->id,
            'borrow_request_item_id' => $line->id,
            'item_id' => $item->id,
            'asset_id' => $asset?->id,
            'quantity' => $asset ? 1 : $quantity,
            'start_at' => $start,
            'end_at' => $end,
        ]);
    }
}
