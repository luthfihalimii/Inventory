<?php

namespace App\Http\Controllers;

use App\Models\InventoryUnit;
use App\Models\MaintenanceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaintenanceRecordController extends Controller
{
    public function index(): View
    {
        return view('maintenance-records.index', [
            'records' => MaintenanceRecord::with('inventoryUnit.inventoryItem')
                ->latest('tanggal_pemeliharaan')->paginate(15),
            'calendarEvents' => MaintenanceRecord::with('inventoryUnit.inventoryItem')->get()->map(fn ($record) => [
                'title' => ($record->inventoryUnit?->kode_unit ?? 'Unit') . ' · ' . $record->status,
                'start' => $record->tanggal_pemeliharaan?->toDateString(),
                'allDay' => true,
            ])->values(),
        ]);
    }

    public function create(): View
    {
        return view('maintenance-records.create', [
            'units' => InventoryUnit::with('inventoryItem')
                ->whereNotIn('status', ['RESERVED', 'BORROWED'])
                ->orderBy('kode_unit')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'inventory_unit_id' => ['required', 'exists:inventory_units,id'],
            'tanggal_pemeliharaan' => ['required', 'date'],
            'deskripsi' => ['required', 'string', 'max:5000'],
            'biaya' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])],
            'kondisi_setelah' => ['nullable', Rule::in(InventoryUnit::CONDITIONS)],
        ]);

        if ($data['status'] === 'COMPLETED' && empty($data['kondisi_setelah'])) {
            throw ValidationException::withMessages([
                'kondisi_setelah' => 'Kondisi akhir wajib diisi ketika pemeliharaan diselesaikan.',
            ]);
        }

        $record = DB::transaction(function () use ($data) {
            $unit = InventoryUnit::whereKey($data['inventory_unit_id'])
                ->lockForUpdate()->firstOrFail();

            if (in_array($unit->status, ['RESERVED', 'BORROWED'], true)) {
                throw ValidationException::withMessages([
                    'inventory_unit_id' => 'Unit yang sedang direservasi atau dipinjam tidak dapat dijadwalkan untuk pemeliharaan.',
                ]);
            }

            if (in_array($data['status'], ['SCHEDULED', 'IN_PROGRESS'], true)
                && $unit->maintenanceRecords()->whereIn('status', ['SCHEDULED', 'IN_PROGRESS'])->exists()) {
                throw ValidationException::withMessages([
                    'inventory_unit_id' => 'Unit ini sudah memiliki pemeliharaan yang belum selesai.',
                ]);
            }

            $record = MaintenanceRecord::create($data);

            if ($record->status === 'COMPLETED') {
                $unit->update([
                    'kondisi' => $record->kondisi_setelah,
                    'terakhir_dipelihara' => $record->tanggal_pemeliharaan,
                ]);
            }

            $unit->fresh()->synchronizeOperationalStatus();

            return $record;
        });

        $this->writeAudit('CREATE', 'MaintenanceRecord', $record->id, null, $record->toArray());

        return redirect()->route('maintenance-records.index')
            ->with('success', 'Riwayat pemeliharaan berhasil ditambahkan.');
    }

    public function update(Request $request, MaintenanceRecord $maintenanceRecord): RedirectResponse
    {
        $data = $request->validate([
            'tanggal_pemeliharaan' => ['required', 'date'],
            'deskripsi' => ['required', 'string', 'max:5000'],
            'biaya' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])],
            'kondisi_setelah' => ['nullable', Rule::in(InventoryUnit::CONDITIONS)],
        ]);

        if ($data['status'] === 'COMPLETED' && empty($data['kondisi_setelah'])) {
            throw ValidationException::withMessages([
                'kondisi_setelah' => 'Kondisi akhir wajib diisi ketika pemeliharaan diselesaikan.',
            ]);
        }

        $old = null;

        DB::transaction(function () use ($maintenanceRecord, $data, &$old) {
            $record = MaintenanceRecord::whereKey($maintenanceRecord->id)
                ->lockForUpdate()->firstOrFail();
            $unit = InventoryUnit::whereKey($record->inventory_unit_id)
                ->lockForUpdate()->firstOrFail();

            if ($record->status === 'CANCELLED' || $record->status === 'COMPLETED') {
                throw ValidationException::withMessages([
                    'status' => 'Catatan pemeliharaan yang sudah selesai atau dibatalkan tidak dapat diubah lagi.',
                ]);
            }

            if (in_array($unit->status, ['RESERVED', 'BORROWED'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Status unit tidak konsisten dengan pemeliharaan aktif. Periksa transaksi unit terlebih dahulu.',
                ]);
            }

            $old = $record->toArray();
            $record->update($data);

            if ($record->status === 'COMPLETED') {
                $unit->update([
                    'kondisi' => $record->kondisi_setelah,
                    'terakhir_dipelihara' => $record->tanggal_pemeliharaan,
                ]);
            }

            $unit->fresh()->synchronizeOperationalStatus();
        });

        $this->writeAudit(
            'UPDATE',
            'MaintenanceRecord',
            $maintenanceRecord->id,
            $old,
            $maintenanceRecord->fresh()->toArray()
        );

        return back()->with('success', 'Riwayat pemeliharaan berhasil diperbarui.');
    }
}
