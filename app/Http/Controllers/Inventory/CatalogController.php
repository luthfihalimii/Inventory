<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Item;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
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
            'canManage' => $canManage,
        ]);
    }
}
