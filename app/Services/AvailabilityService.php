<?php

namespace App\Services;

use App\Models\Item;

class AvailabilityService
{
    /**
     * Stock snapshot for an item.
     *
     * @return array{total: int, available: int, reserved: int, borrowed: int, damaged: int, maintenance: int, lost: int, inactive: int}
     */
    public function summary(Item $item): array
    {
        $isActive = $item->status === 'active';

        if ($item->tracking_method === 'serialized') {
            $counts = $item->assets()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            return [
                'total' => (int) $counts->sum(),
                'available' => $isActive ? (int) ($counts['available'] ?? 0) : 0,
                // ponytail: reservations/loans arrive in Sprint 4–5; per-period counts stay zero until then.
                'reserved' => 0,
                'borrowed' => (int) ($counts['borrowed'] ?? 0),
                'damaged' => (int) ($counts['damaged'] ?? 0),
                'maintenance' => (int) ($counts['maintenance'] ?? 0),
                'lost' => (int) ($counts['lost'] ?? 0),
                'inactive' => (int) ($counts['inactive'] ?? 0),
            ];
        }

        $total = (int) $item->quantity_total;
        $blocked = $this->blockedQuantity($item);

        return [
            'total' => $total,
            'available' => $isActive ? max(0, $total - $blocked) : 0,
            'reserved' => 0,
            'borrowed' => 0,
            'damaged' => $blocked,
            'maintenance' => 0,
            'lost' => 0,
            'inactive' => 0,
        ];
    }

    /**
     * Units blocked by open damage/loss/maintenance reports (quantity-tracked items).
     */
    public function blockedQuantity(Item $item): int
    {
        return (int) $item->damageReports()
            ->where('status', 'open')
            ->sum('quantity');
    }

    public function isAvailable(Item $item, int $quantity = 1): bool
    {
        if ($item->status !== 'active') {
            return false;
        }

        return $this->summary($item)['available'] >= $quantity;
    }
}
