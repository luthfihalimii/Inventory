<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\ItemRequest;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Services\AuditLogger;
use App\Services\AvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request, AvailabilityService $availability): Response
    {
        Gate::authorize('manage-inventory');

        $search = $request->input('q');
        $search = is_string($search) ? trim($search) : '';
        $status = $request->input('status');
        $categoryId = $request->input('category_id');

        $items = Item::query()
            ->with(['category', 'location'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('code', 'ilike', "%{$search}%")))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $items->through(fn (Item $item) => [
            ...$item->only([
                'id', 'code', 'name', 'tracking_method', 'quantity_total', 'price',
                'acquired_at', 'status', 'notes',
            ]),
            'category' => $item->category?->only(['id', 'name']),
            'location' => $item->location?->only(['id', 'name', 'code']),
            'availability' => $availability->summary($item),
        ]);

        return Inertia::render('admin/inventory/items/Index', [
            'items' => $items,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('name')->get(['id', 'name', 'code']),
            'filters' => [
                'q' => $search,
                'status' => is_string($status) ? $status : '',
                'category_id' => $categoryId,
            ],
        ]);
    }

    public function store(ItemRequest $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $item = Item::create([
            ...$request->validated(),
            'qr_token' => Str::random(40),
        ]);

        $audit->record('item.created', $item, ['code' => $item->code]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Barang dibuat.']);

        return back();
    }

    public function update(
        ItemRequest $request,
        Item $item,
        AuditLogger $audit,
        AvailabilityService $availability,
    ): RedirectResponse {
        Gate::authorize('manage-inventory');

        $data = $request->validated();

        if (($item->assets()->exists() || $item->damageReports()->exists()) && $data['tracking_method'] !== $item->tracking_method) {
            throw ValidationException::withMessages([
                'tracking_method' => 'Metode pelacakan tidak bisa diubah karena barang sudah punya unit atau laporan.',
            ]);
        }

        if ($item->tracking_method === 'quantity') {
            $blocked = $availability->blockedQuantity($item);

            if ((int) $data['quantity_total'] < $blocked) {
                throw ValidationException::withMessages([
                    'quantity_total' => "Jumlah tidak boleh di bawah laporan terbuka ({$blocked}).",
                ]);
            }
        }

        $before = $item->only([
            'code', 'name', 'category_id', 'location_id', 'tracking_method',
            'quantity_total', 'price', 'acquired_at', 'status', 'notes',
        ]);

        $item->update($data);

        $audit->record('item.updated', $item, [], $before, $item->only(array_keys($before)));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Barang diperbarui.']);

        return back();
    }

    public function qr(Item $item): Response
    {
        Gate::authorize('manage-inventory');

        return Inertia::render('admin/inventory/items/Qr', [
            'item' => [
                ...$item->only(['id', 'code', 'name', 'qr_token', 'tracking_method']),
                'assets' => $item->assets()->orderBy('asset_code')->get(['id', 'asset_code', 'qr_token']),
            ],
        ]);
    }
}
