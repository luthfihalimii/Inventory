<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    public function index(Request $request): View
    {
        $isManager = $request->user()->roles()->whereIn('nama', ['ADMIN', 'SUPER_ADMIN'])->exists();
        $canManage = $request->user()->roles()->where('nama', 'ADMIN')->exists();
        $query = InventoryItem::with(['category', 'units.maintenanceRecords'])->withCount([
            'units as total_units',
            'units as available_units' => fn ($units) => $units->where('status', 'AVAILABLE')->where('kondisi', '!=', 'DAMAGED'),
            'units as borrowed_units' => fn ($units) => $units->where('status', 'BORROWED'),
        ]);
        if (! $isManager) $query->where('status', 'ACTIVE');
        if ($request->filled('q')) $query->where(fn ($q) => $q->where('nama', 'like', '%'.$request->q.'%')->orWhere('kode_item', 'like', '%'.$request->q.'%'));
        if ($request->filled('kategori')) $query->where('kategori_id', $request->kategori);
        if ($isManager && $request->filled('status')) $query->where('status', $request->status);

        return view('inventory-items.index', [
            'items' => $query->latest()->paginate(15)->withQueryString(),
            'categories' => ItemCategory::orderBy('nama')->get(),
            'isManager' => $isManager,
            'canManage' => $canManage,
        ]);
    }

    public function create(): View
    {
        return view('inventory-items.create', [
            'categories' => ItemCategory::where('status_aktif', true)->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode_item' => ['required', 'string', 'max:50', 'unique:inventory_items,kode_item'],
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string'],
            'kategori_id' => ['required', 'exists:item_categories,id'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);

        if (! ItemCategory::whereKey($data['kategori_id'])->where('status_aktif', true)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'kategori_id' => 'Kategori harus aktif untuk digunakan pada inventaris baru.',
            ]);
        }

        $item = InventoryItem::create($data);
        $this->writeAudit('CREATE', 'InventoryItem', $item->id, null, $item->toArray());

        return redirect()->route('inventory-items.index')->with('success', 'Jenis inventaris berhasil ditambahkan.');
    }

    public function show(Request $request, InventoryItem $inventoryItem): View
    {
        $isManager = $request->user()->roles()->whereIn('nama', ['ADMIN', 'SUPER_ADMIN'])->exists();
        abort_unless($isManager || $inventoryItem->status === 'ACTIVE', 404);

        $item = $inventoryItem->load(['category', 'units.maintenanceRecords'])->loadCount([
            'units as total_units',
            'units as available_units' => fn ($units) => $units->where('status', 'AVAILABLE')->where('kondisi', '!=', 'DAMAGED'),
            'units as borrowed_units' => fn ($units) => $units->where('status', 'BORROWED'),
        ]);
        $availabilityEvents = $item->loanDetails()->with('loanRequest')
            ->whereHas('loanRequest', fn ($q) => $q->whereIn('status', ['APPROVED', 'BORROWED', 'RETURN_PENDING', 'RETURN_ISSUE']))
            ->get()->map(fn ($detail) => [
                'title' => 'Peminjaman disetujui · ' . $detail->jumlah . ' unit',
                'start' => $detail->loanRequest->waktu_mulai?->toIso8601String(),
                'end' => $detail->loanRequest->waktu_selesai?->toIso8601String(),
                'display' => 'block',
                'backgroundColor' => '#2563EB',
                'borderColor' => '#2563EB',
            ])->values();

        $maintenanceEvents = $item->units->flatMap(fn ($unit) => $unit->maintenanceRecords
            ->whereIn('status', ['SCHEDULED', 'IN_PROGRESS'])
            ->map(fn ($record) => [
                'title' => 'Pemeliharaan · ' . $unit->kode_unit,
                'start' => $record->tanggal_pemeliharaan?->toDateString(),
                'allDay' => true,
                'display' => 'block',
                'backgroundColor' => '#D97706',
                'borderColor' => '#D97706',
            ]));
        $availabilityEvents = $availabilityEvents->concat($maintenanceEvents)->values();
        return view('inventory-items.show', compact('item', 'availabilityEvents'));
    }

    public function edit(InventoryItem $inventoryItem): View
    {
        return view('inventory-items.edit', [
            'item' => $inventoryItem,
            'categories' => ItemCategory::where('status_aktif', true)->orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $data = $request->validate([
            'kode_item' => ['required', 'string', 'max:50', Rule::unique('inventory_items', 'kode_item')->ignore($inventoryItem->id)],
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string'],
            'kategori_id' => ['required', 'exists:item_categories,id'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);

        if ($data['status'] === 'INACTIVE' && $inventoryItem->units()->whereIn('status', ['RESERVED', 'BORROWED'])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => 'Jenis inventaris tidak dapat dinonaktifkan selama masih ada unit yang direservasi atau dipinjam.',
            ]);
        }

        if ((int) $data['kategori_id'] !== (int) $inventoryItem->kategori_id
            && ! ItemCategory::whereKey($data['kategori_id'])->where('status_aktif', true)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'kategori_id' => 'Kategori tujuan harus aktif.',
            ]);
        }

        $old = $inventoryItem->toArray();
        $inventoryItem->update($data);
        $this->writeAudit('UPDATE', 'InventoryItem', $inventoryItem->id, $old, $inventoryItem->fresh()->toArray());

        return redirect()->route('inventory-items.index')->with('success', 'Inventaris berhasil diperbarui.');
    }


    public function bulkDeactivate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:inventory_items,id'],
        ]);

        $processed = DB::transaction(function () use ($data) {
            $items = InventoryItem::whereIn('id', $data['ids'])->lockForUpdate()->get();
            foreach ($items as $item) {
                if ($item->units()->whereIn('status', ['RESERVED', 'BORROWED'])->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'ids' => "{$item->nama} masih memiliki unit yang direservasi atau dipinjam. Tidak ada perubahan yang disimpan.",
                    ]);
                }
            }
            foreach ($items as $item) {
                if ($item->status === 'ACTIVE') {
                    $item->update(['status' => 'INACTIVE']);
                    $this->writeAudit('BULK_DEACTIVATE', 'InventoryItem', $item->id, ['status' => 'ACTIVE'], ['status' => 'INACTIVE']);
                }
            }
            return $items->count();
        });

        return back()->with('success', $processed.' jenis inventaris diproses. Item yang sudah nonaktif tidak diubah.');
    }

    public function deactivate(InventoryItem $inventoryItem): RedirectResponse
    {
        $hasOpenUnits = $inventoryItem->units()
            ->whereIn('status', ['RESERVED', 'BORROWED'])
            ->exists();

        if ($hasOpenUnits) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => 'Jenis inventaris tidak dapat dinonaktifkan selama masih ada unit yang direservasi atau dipinjam.',
            ]);
        }

        $old = $inventoryItem->only(['status']);
        $inventoryItem->update(['status' => 'INACTIVE']);
        $this->writeAudit('DEACTIVATE', 'InventoryItem', $inventoryItem->id, $old, ['status' => 'INACTIVE']);

        return back()->with('success', 'Jenis inventaris dinonaktifkan.');
    }
}
