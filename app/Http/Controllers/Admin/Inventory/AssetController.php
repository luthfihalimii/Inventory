<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\AssetRequest;
use App\Models\Asset;
use App\Models\Item;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-inventory');

        $search = $request->input('q');
        $search = is_string($search) ? trim($search) : '';
        $itemId = $request->input('item_id');

        return Inertia::render('admin/inventory/assets/Index', [
            'assets' => Asset::query()
                ->with('item:id,code,name,tracking_method')
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('asset_code', 'ilike', "%{$search}%")
                    ->orWhere('serial_number', 'ilike', "%{$search}%")))
                ->when($itemId, fn ($query) => $query->where('item_id', $itemId))
                ->orderBy('asset_code')
                ->paginate(20)
                ->withQueryString(),
            'items' => Item::where('tracking_method', 'serialized')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'filters' => ['q' => $search, 'item_id' => $itemId],
        ]);
    }

    public function store(AssetRequest $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $item = Item::whereKey($request->validated('item_id'))->firstOrFail();

        if ($item->tracking_method !== 'serialized') {
            throw ValidationException::withMessages([
                'item_id' => 'Unit hanya bisa ditambahkan ke barang serialized.',
            ]);
        }

        $asset = Asset::create([
            ...$request->validated(),
            'qr_token' => Str::random(40),
        ]);

        $audit->record('asset.created', $asset, ['asset_code' => $asset->asset_code]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit dibuat.']);

        return back();
    }

    public function update(AssetRequest $request, Asset $asset, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $data = $request->validated();
        unset($data['item_id']);

        if (
            in_array($data['status'], ['available', 'borrowed'], true)
            && $asset->damageReports()->where('status', 'open')->exists()
        ) {
            throw ValidationException::withMessages([
                'status' => 'Unit punya laporan terbuka; selesaikan laporan dulu.',
            ]);
        }

        $before = $asset->only(['asset_code', 'serial_number', 'condition', 'status', 'notes']);

        $asset->update($data);

        $audit->record('asset.updated', $asset, ['asset_code' => $asset->asset_code], $before, $asset->only(array_keys($before)));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit diperbarui.']);

        return back();
    }
}
