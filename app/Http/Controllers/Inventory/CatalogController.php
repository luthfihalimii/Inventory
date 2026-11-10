<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use App\Models\DamageReport;
use App\Models\Item;
use App\Models\LoanItem;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request, AvailabilityService $availability): Response
    {
        $search = $request->input('q');
        $search = is_string($search) ? trim($search) : '';
        $categoryId = $request->input('category_id');
        $status = $request->input('status');

        $items = Item::query()
            ->with(['category:id,name', 'location:id,name,code'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('code', 'ilike', "%{$search}%")))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $items->through(fn (Item $item) => [
            ...$item->only(['id', 'code', 'name', 'tracking_method', 'quantity_total', 'status']),
            'category' => $item->category?->only(['id', 'name']),
            'location' => $item->location?->only(['id', 'name', 'code']),
            'availability' => $availability->summary($item),
        ]);

        return Inertia::render('inventory/Index', [
            'items' => $items,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'q' => $search,
                'category_id' => $categoryId,
                'status' => is_string($status) ? $status : '',
            ],
        ]);
    }

    public function show(Request $request, Item $item, AvailabilityService $availability): Response
    {
        $canManage = $request->user()->can('manage-inventory');

        return Inertia::render('inventory/Show', [
            'item' => [
                ...$item->only(['id', 'code', 'name', 'tracking_method', 'quantity_total', 'status']),
                'price' => $canManage ? $item->price : null,
                'acquired_at' => $canManage ? $item->getRawOriginal('acquired_at') : null,
                'notes' => $canManage ? $item->notes : null,
                'category' => $item->category?->only(['id', 'name']),
                'location' => $item->location?->only(['id', 'name', 'code']),
                'assets' => $item->tracking_method === 'serialized'
                    ? $item->assets()->orderBy('asset_code')->get(['id', 'asset_code', 'serial_number', 'condition', 'status'])
                        ->map(fn (Asset $asset) => [
                            ...$asset->only(['id', 'asset_code', 'condition', 'status']),
                            'serial_number' => $canManage ? $asset->serial_number : null,
                        ])
                    : [],
            ],
            'availability' => $availability->summary($item),
            'schedule' => $this->schedule($item),
            'maintenanceHistory' => $canManage
                ? DamageReport::query()
                    ->where('item_id', $item->id)
                    ->with('resolver:id,name')
                    ->latest('reported_at')
                    ->limit(20)
                    ->get()
                    ->map(fn (DamageReport $report) => [
                        ...$report->only([
                            'id', 'type', 'quantity', 'description', 'status',
                            'resolution', 'resolution_notes', 'cost', 'vendor',
                            'reported_at', 'resolved_at',
                        ]),
                        'resolver' => $report->resolver?->only(['id', 'name']),
                    ])
                    ->values()
                : [],
            'canManage' => $canManage,
        ]);
    }

    /**
     * Busy ranges for the next 90 days: active reservations and open loans.
     *
     * @return array<int, array{start: string, end: string, quantity: int, kind: string}>
     */
    private function schedule(Item $item): array
    {
        $windowStart = now()->startOfDay();
        $windowEnd = now()->addDays(90)->endOfDay();
        $ranges = [];

        $reservations = $item->reservations()
            ->where('status', 'active')
            ->where('start_at', '<', $windowEnd)
            ->where('end_at', '>', $windowStart)
            ->get(['start_at', 'end_at', 'quantity']);

        foreach ($reservations as $reservation) {
            $ranges[] = [
                'start' => $reservation->start_at->toIso8601String(),
                'end' => $reservation->end_at->toIso8601String(),
                'quantity' => $reservation->quantity,
                'kind' => 'reservation',
            ];
        }

        $lines = LoanItem::query()
            ->where('item_id', $item->id)
            ->whereHas('loanTransaction', fn ($query) => $query
                ->whereIn('status', ['borrowed', 'overdue', 'return_pending_verification']))
            ->with('loanTransaction:id,status,checked_out_at,due_at')
            ->get();

        foreach ($lines as $line) {
            $quantity = $line->quantity - $line->quantity_returned;

            if ($quantity < 1 || $line->loanTransaction === null) {
                continue;
            }

            $start = $line->loanTransaction->checked_out_at ?? $windowStart;
            $end = $line->loanTransaction->due_at ?? $windowEnd;

            if ($start->greaterThanOrEqualTo($windowEnd) || $end->lessThanOrEqualTo($windowStart)) {
                continue;
            }

            $ranges[] = [
                'start' => Carbon::instance($start)->toIso8601String(),
                'end' => Carbon::instance($end)->toIso8601String(),
                'quantity' => $quantity,
                'kind' => 'loan',
            ];
        }

        return $ranges;
    }
}
