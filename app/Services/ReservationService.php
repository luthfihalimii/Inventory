<?php

namespace App\Services;

use App\Models\BorrowRequest;
use App\Models\Item;
use App\Models\Reservation;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * Allocate reservations for an approved request. Must run inside a transaction.
     */
    public function reserve(BorrowRequest $borrowRequest): void
    {
        $lines = $borrowRequest->items()->orderBy('item_id')->get();

        // Serializes concurrent approvals for the same items.
        $items = Item::whereIn('id', $lines->pluck('item_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($lines as $line) {
            $item = $items[$line->item_id];
            $available = $this->availability->availableForPeriod($item, $borrowRequest->pickup_at, $borrowRequest->return_at);

            if ($item->status !== 'active' || $available < $line->quantity) {
                throw ValidationException::withMessages([
                    'items' => "Stok {$item->name} tidak cukup untuk periode ini (tersedia {$available}).",
                ]);
            }

            if ($item->tracking_method === 'serialized') {
                $this->reserveAssets($borrowRequest, $line->id, $item, $line->quantity);

                continue;
            }

            Reservation::create([
                'borrow_request_id' => $borrowRequest->id,
                'borrow_request_item_id' => $line->id,
                'item_id' => $item->id,
                'asset_id' => null,
                'quantity' => $line->quantity,
                'start_at' => $borrowRequest->pickup_at,
                'end_at' => $borrowRequest->return_at,
                'status' => 'active',
            ]);
        }
    }

    public function release(BorrowRequest $borrowRequest): void
    {
        $borrowRequest->reservations()->where('status', 'active')->update(['status' => 'released']);
    }

    private function reserveAssets(BorrowRequest $borrowRequest, int $requestItemId, Item $item, int $quantity): void
    {
        $assets = $item->assets()
            ->whereNotIn('status', ['borrowed', 'damaged', 'maintenance', 'lost', 'inactive'])
            ->whereDoesntHave('reservations', fn ($query) => $query
                ->where('status', 'active')
                ->where('start_at', '<', $borrowRequest->return_at)
                ->where('end_at', '>', $borrowRequest->pickup_at))
            ->orderBy('id')
            ->limit($quantity)
            ->lockForUpdate()
            ->get();

        if ($assets->count() < $quantity) {
            throw ValidationException::withMessages([
                'items' => "Unit {$item->name} tidak cukup untuk periode ini.",
            ]);
        }

        foreach ($assets as $asset) {
            Reservation::create([
                'borrow_request_id' => $borrowRequest->id,
                'borrow_request_item_id' => $requestItemId,
                'item_id' => $item->id,
                'asset_id' => $asset->id,
                'quantity' => 1,
                'start_at' => $borrowRequest->pickup_at,
                'end_at' => $borrowRequest->return_at,
                'status' => 'active',
            ]);
        }
    }
}
