<?php

namespace App\Services;

use App\Models\Item;
use App\Models\LoanItem;
use Carbon\CarbonInterface;

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
        $start = now();
        $end = now()->addSecond();

        if ($item->tracking_method === 'serialized') {
            $counts = $item->assets()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $reserved = (int) $item->reservations()
                ->whereNotNull('asset_id')
                ->where('status', 'active')
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start)
                ->count();

            return [
                'total' => (int) $counts->sum(),
                'available' => $isActive ? max(0, (int) ($counts['available'] ?? 0) - $reserved) : 0,
                'reserved' => $reserved,
                'borrowed' => (int) ($counts['borrowed'] ?? 0),
                'damaged' => (int) ($counts['damaged'] ?? 0),
                'maintenance' => (int) ($counts['maintenance'] ?? 0),
                'lost' => (int) ($counts['lost'] ?? 0),
                'inactive' => (int) ($counts['inactive'] ?? 0),
            ];
        }

        $total = (int) $item->quantity_total;
        $blocked = $this->blockedQuantity($item);
        $reserved = (int) $item->reservations()
            ->where('status', 'active')
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->sum('quantity');
        // Real-time stock: overdue loans are still physically out, so no due_at cut-off here.
        $borrowed = $this->currentOutstandingLoanQuantity($item);

        return [
            'total' => $total,
            'available' => $isActive ? max(0, $total - $blocked - $reserved - $borrowed) : 0,
            'reserved' => $reserved,
            'borrowed' => $borrowed,
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

    /**
     * Units free for the given period, ignoring today's physical status where possible.
     */
    public function availableForPeriod(Item $item, CarbonInterface $start, CarbonInterface $end): int
    {
        if ($item->status !== 'active') {
            return 0;
        }

        if ($item->tracking_method === 'serialized') {
            $busyAssetIds = LoanItem::query()
                ->whereNotNull('asset_id')
                ->whereHas('loanTransaction', fn ($query) => $query
                    ->whereIn('status', ['borrowed', 'overdue', 'return_pending_verification'])
                    ->where(fn ($query) => $query->whereNull('checked_out_at')->orWhere('checked_out_at', '<', $end))
                    // Overdue units are still physically out: they stay blocked whatever the requested period.
                    ->where(fn ($query) => $query
                        ->where('status', 'overdue')
                        ->orWhere(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>', $start))))
                ->pluck('asset_id');

            return $item->assets()
                ->whereNotIn('status', ['damaged', 'maintenance', 'lost', 'inactive'])
                ->whereNotIn('id', $busyAssetIds)
                ->whereDoesntHave('reservations', fn ($query) => $query
                    ->where('status', 'active')
                    ->where('start_at', '<', $end)
                    ->where('end_at', '>', $start))
                ->count();
        }

        $reserved = (int) $item->reservations()
            ->where('status', 'active')
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->sum('quantity');

        $borrowed = $this->outstandingLoanQuantity($item, $start, $end);

        return max(0, (int) $item->quantity_total - $this->blockedQuantity($item) - $reserved - $borrowed);
    }

    private function outstandingLoanQuantity(Item $item, CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) LoanItem::query()
            ->where('item_id', $item->id)
            ->whereHas('loanTransaction', fn ($query) => $query
                ->whereIn('status', ['borrowed', 'overdue', 'return_pending_verification'])
                ->where(fn ($query) => $query->whereNull('checked_out_at')->orWhere('checked_out_at', '<', $end))
                // Overdue units are still physically out: they stay blocked whatever the requested period.
                ->where(fn ($query) => $query
                    ->where('status', 'overdue')
                    ->orWhere(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>', $start))))
            ->sum('quantity');
    }

    /**
     * Units currently out with a member, including overdue loans.
     */
    private function currentOutstandingLoanQuantity(Item $item): int
    {
        return (int) LoanItem::query()
            ->where('item_id', $item->id)
            ->whereHas('loanTransaction', fn ($query) => $query
                ->whereIn('status', ['borrowed', 'overdue', 'return_pending_verification']))
            ->sum('quantity');
    }
}
