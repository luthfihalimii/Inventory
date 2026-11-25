<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryUnitController extends Controller
{
    public function index(Request $request): View
    {
        $query = InventoryUnit::with('inventoryItem');
        if ($request->filled('q')) $query->where(fn ($q) => $q->where('kode_unit','like','%'.$request->q.'%')->orWhereHas('inventoryItem',fn($item)=>$item->where('nama','like','%'.$request->q.'%')));
        if ($request->filled('status')) $query->where('status',$request->status);
        if ($request->filled('kondisi')) $query->where('kondisi',$request->kondisi);
        return view('inventory-units.index', ['units' => $query->latest()->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        return view('inventory-units.create', [
            'items' => InventoryItem::where('status', 'ACTIVE')->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'kode_unit' => ['required', 'string', 'max:60', 'unique:inventory_units,kode_unit'],
            'harga_pembelian' => ['nullable', 'numeric', 'min:0'],
            'tanggal_pembelian' => ['nullable', 'date'],
            'terakhir_dipelihara' => ['nullable', 'date'],
            'jadwal_pemeliharaan_berikutnya' => ['nullable', 'date', 'after_or_equal:terakhir_dipelihara'],
            'kondisi' => ['required', Rule::in(InventoryUnit::CONDITIONS)],
            'catatan' => ['nullable', 'string'],
        ]);

        $itemIsActive = InventoryItem::whereKey($data['inventory_item_id'])->where('status', 'ACTIVE')->exists();
        if (! $itemIsActive) {
            throw ValidationException::withMessages([
                'inventory_item_id' => 'Jenis inventaris tidak aktif dan tidak dapat menerima unit baru.',
            ]);
        }

        $data['status'] = $data['kondisi'] === 'DAMAGED' ? 'MAINTENANCE' : 'AVAILABLE';

        $unit = DB::transaction(fn () => InventoryUnit::create($data));

        $this->writeAudit('CREATE', 'InventoryUnit', $unit->id, null, $unit->toArray());

        return redirect()->route('inventory-units.index')
            ->with('success', 'Unit inventaris berhasil ditambahkan.');
    }

    public function edit(InventoryUnit $inventoryUnit): View
    {
        return view('inventory-units.edit', [
            'unit' => $inventoryUnit,
            'items' => InventoryItem::where('status', 'ACTIVE')->orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, InventoryUnit $inventoryUnit): RedirectResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'kode_unit' => ['required', 'string', 'max:60', Rule::unique('inventory_units', 'kode_unit')->ignore($inventoryUnit->id)],
            'harga_pembelian' => ['nullable', 'numeric', 'min:0'],
            'tanggal_pembelian' => ['nullable', 'date'],
            'terakhir_dipelihara' => ['nullable', 'date'],
            'jadwal_pemeliharaan_berikutnya' => ['nullable', 'date', 'after_or_equal:terakhir_dipelihara'],
            'kondisi' => ['required', Rule::in(InventoryUnit::CONDITIONS)],
            'catatan' => ['nullable', 'string'],
        ]);

        $old = null;

        DB::transaction(function () use ($inventoryUnit, $data, &$old) {
            $unit = InventoryUnit::whereKey($inventoryUnit->id)->lockForUpdate()->firstOrFail();
            $old = $unit->toArray();

            $conditionChanged = $unit->kondisi !== $data['kondisi'];
            $itemChanged = (int) $unit->inventory_item_id !== (int) $data['inventory_item_id'];

            if ($itemChanged) {
                $hasLoanHistory = $unit->loanDetailUnits()->exists();
                if (in_array($unit->status, ['RESERVED', 'BORROWED'], true) || $hasLoanHistory) {
                    throw ValidationException::withMessages([
                        'inventory_item_id' => 'Jenis barang tidak dapat diubah setelah unit memiliki riwayat peminjaman atau sedang direservasi/dipinjam.',
                    ]);
                }

                $targetItemIsActive = InventoryItem::whereKey($data['inventory_item_id'])
                    ->where('status', 'ACTIVE')->exists();
                if (! $targetItemIsActive) {
                    throw ValidationException::withMessages([
                        'inventory_item_id' => 'Jenis inventaris tujuan harus berstatus aktif.',
                    ]);
                }
            }

            if ($conditionChanged && in_array($unit->status, ['RESERVED', 'BORROWED'], true)) {
                throw ValidationException::withMessages([
                    'kondisi' => 'Kondisi fisik unit yang sedang direservasi atau dipinjam harus diperbarui melalui pemeriksaan pengembalian.',
                ]);
            }

            if ($conditionChanged && $unit->kondisi === 'DAMAGED' && $data['kondisi'] !== 'DAMAGED') {
                throw ValidationException::withMessages([
                    'kondisi' => 'Kondisi DAMAGED hanya dapat dipulihkan melalui penyelesaian pemeliharaan.',
                ]);
            }

            if ($conditionChanged && $unit->hasOpenReturnIssue()) {
                throw ValidationException::withMessages([
                    'kondisi' => 'Kondisi unit yang masih memiliki temuan pengembalian harus diperbarui melalui pemeriksaan ulang.',
                ]);
            }

            if ($conditionChanged && $unit->hasActiveMaintenance() && $data['kondisi'] !== 'DAMAGED') {
                throw ValidationException::withMessages([
                    'kondisi' => 'Kondisi akhir unit harus dicatat melalui penyelesaian pemeliharaan.',
                ]);
            }

            $unit->fill($data);
            $unit->save();
            $unit->synchronizeOperationalStatus();
        });

        $this->writeAudit(
            'UPDATE',
            'InventoryUnit',
            $inventoryUnit->id,
            $old,
            $inventoryUnit->fresh()->toArray()
        );

        return redirect()->route('inventory-units.index')
            ->with('success', 'Data unit inventaris berhasil diperbarui.');
    }
}
