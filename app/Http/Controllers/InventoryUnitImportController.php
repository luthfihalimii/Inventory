<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Support\SpreadsheetRows;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryUnitImportController extends Controller
{
    public function index(): View { return view('inventory-units.import'); }

    public function template(Request $request)
    {
        $extension = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';
        $filename = "template-import-unit-inventaris.{$extension}";

        return response()->download(
            public_path("templates/{$filename}"),
            $filename,
            ['Content-Type' => $extension === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function export(Request $request): StreamedResponse|View
    {
        if ($request->query('format') === 'pdf') {
            $units = InventoryUnit::with('inventoryItem')->orderBy('kode_unit')->get();
            $rows = $units->map(fn ($unit) => [
                $unit->kode_unit, $unit->inventoryItem?->nama ?? '—',
                $unit->kondisi, $unit->status,
                $unit->harga_pembelian ? 'Rp ' . number_format($unit->harga_pembelian, 0, ',', '.') : '—',
            ]);
            return view('reports.print', ['reportTitle' => 'Laporan Unit Inventaris',
                'subtitle' => 'Daftar unit fisik, kondisi, status, dan harga pembelian.',
                'columns' => ['Kode unit', 'Jenis barang', 'Kondisi', 'Status', 'Harga pembelian'],
                'rows' => $rows, 'summary' => ['Jumlah unit' => $units->count(), 'Tersedia' => $units->where('status','AVAILABLE')->count()]]);
        }
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
            \App\Support\CsvSafety::write($out, ['kode_unit', 'kode_item', 'nama_item', 'harga_pembelian', 'tanggal_pembelian', 'kondisi', 'status', 'catatan']);
            InventoryUnit::with('inventoryItem')->orderBy('kode_unit')->chunk(500, function ($units) use ($out) {
                foreach ($units as $unit) \App\Support\CsvSafety::write($out, [$unit->kode_unit, $unit->inventoryItem?->kode_item, $unit->inventoryItem?->nama, $unit->harga_pembelian, $unit->tanggal_pembelian?->format('Y-m-d'), $unit->kondisi, $unit->status, $unit->catatan]);
            });
            fclose($out);
        }, 'ent-inventory-units-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240']]);
        $isXlsx = strtolower($request->file('file')->getClientOriginalExtension()) === 'xlsx';
        if ($isXlsx) {
            try {
                $allRows = SpreadsheetRows::readXlsx($request->file('file')->getRealPath());
            } catch (\Throwable $e) {
                \Log::warning('Inventory unit XLSX rejected', ['exception' => get_class($e)]);
                return back()->withErrors(['file' => 'File Excel tidak valid atau melewati batas pemrosesan. Periksa template dan ukuran file.']);
            }
            $header = array_shift($allRows);
            $dataRows = $allRows;
        } else {
            $handle = fopen($request->file('file')->getRealPath(), 'r');
            $header = fgetcsv($handle);
            $dataRows = [];
            while (($lineValues = fgetcsv($handle)) !== false) $dataRows[] = $lineValues;
            fclose($handle);
        }

        if (! $header) return back()->withErrors(['file' => 'File kosong atau tidak memiliki header.']);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $required = ['kode_item', 'nama_item', 'jumlah', 'harga_pembelian', 'tanggal_pembelian', 'kondisi', 'status', 'terakhir_dipelihara', 'jadwal_pemeliharaan_berikutnya', 'catatan'];
        if (array_diff($required, $header)) {
            return back()->withErrors(['file' => 'Header tidak sesuai. Gunakan template dengan kolom kode_item, nama_item, jumlah, harga_pembelian, tanggal_pembelian, kondisi, status, terakhir_dipelihara, jadwal_pemeliharaan_berikutnya, dan catatan.']);
        }
        if (count($dataRows) > 5000) return back()->withErrors(['file' => 'Maksimal 5.000 baris jenis barang per proses import.']);

        $rows = [];
        $errors = [];
        $seenItems = [];
        $totalUnits = 0;
        $skipped = 0;
        $line = 1;

        foreach ($dataRows as $values) {
            $line++;
            if (count(array_filter($values, fn ($value) => $value !== null && $value !== '')) === 0) continue;
            $row = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), null));
            foreach ($row as $key => $value) $row[$key] = is_string($value) ? trim($value) : $value;

            if (empty($row['kode_item']) || empty($row['nama_item'])) {
                $errors[] = "Baris {$line}: kode_item dan nama_item wajib diisi.";
                continue;
            }
            $itemCode = (string) $row['kode_item'];
            if (isset($seenItems[$itemCode])) {
                $errors[] = "Baris {$line}: kode_item {$itemCode} muncul lebih dari sekali. Gabungkan jumlahnya dalam satu baris.";
                continue;
            }
            $seenItems[$itemCode] = true;

            $item = InventoryItem::where('kode_item', $itemCode)->where('status', 'ACTIVE')->first();
            if (! $item) {
                $errors[] = "Baris {$line}: kode_item {$itemCode} tidak ditemukan atau tidak aktif. Buat jenis inventaris terlebih dahulu.";
                continue;
            }
            if (mb_strtolower((string) $row['nama_item']) !== mb_strtolower($item->nama)) {
                $errors[] = "Baris {$line}: nama_item tidak cocok dengan kode_item {$itemCode}. Nama pada master: {$item->nama}.";
                continue;
            }

            $validator = Validator::make($row, [
                'harga_pembelian' => ['nullable', 'numeric', 'min:0'],
                'kondisi' => ['required', 'in:GOOD,MINOR_DAMAGE,DAMAGED'],
                'status' => ['required', 'in:AVAILABLE,MAINTENANCE'],
                'tanggal_pembelian' => ['nullable', 'date'],
                'terakhir_dipelihara' => ['nullable', 'date'],
                'jadwal_pemeliharaan_berikutnya' => ['nullable', 'date'],
                'catatan' => ['nullable', 'string', 'max:1000'],
                'jumlah' => ['required', 'integer', 'min:1', 'max:5000'],
            ]);
            if ($validator->fails()) {
                $errors[] = "Baris {$line}: " . implode(' ', $validator->errors()->all());
                continue;
            }


            $totalUnits += (int) $row['jumlah'];
            if ($totalUnits > 5000) {
                $errors[] = 'Total unit yang akan dibuat maksimal 5.000 unit per proses import.';
                break;
            }
            $rows[] = [
                'line' => $line,
                'item' => $item,
                'quantity' => (int) $row['jumlah'],
                'price' => $row['harga_pembelian'] !== '' ? $row['harga_pembelian'] : null,
                'purchase_date' => $row['tanggal_pembelian'] ?: null,
                'last_maintenance' => $row['terakhir_dipelihara'] ?: null,
                'next_maintenance' => $row['jadwal_pemeliharaan_berikutnya'] ?: null,
                'condition' => $row['kondisi'],
                'status' => $row['kondisi'] === 'DAMAGED' ? 'MAINTENANCE' : $row['status'],
                'notes' => $row['catatan'] !== '' ? $row['catatan'] : null,
            ];
        }

        if ($errors) return back()->withErrors(['file' => 'Impor dibatalkan tanpa menyimpan data. ' . implode(' | ', array_slice($errors, 0, 12))]);
        if (! $rows) return back()->with('success', 'Tidak ada data baru untuk diimpor.');

        $created = 0;
        DB::transaction(function () use ($rows, &$created) {
            foreach ($rows as $row) {
                $item = $row['item'];
                $sequence = 1;
                for ($i = 0; $i < $row['quantity']; $i++) {
                    do {
                        $unitCode = sprintf('ENT-UNIT-%04d-%03d', $item->id, $sequence++);
                    } while (InventoryUnit::where('kode_unit', $unitCode)->exists());

                    $unit = InventoryUnit::create([
                        'inventory_item_id' => $item->id,
                        'kode_unit' => $unitCode,
                        'harga_pembelian' => $row['price'],
                        'tanggal_pembelian' => $row['purchase_date'],
                        'terakhir_dipelihara' => $row['last_maintenance'],
                        'jadwal_pemeliharaan_berikutnya' => $row['next_maintenance'],
                        'kondisi' => $row['condition'],
                        'status' => $row['status'],
                        'catatan' => $row['notes'],
                    ]);
                    AuditLog::create([
                        'actor_id' => auth()->id(),
                        'manager_term_id' => auth()->user()?->activeManagerTerm()?->id,
                        'aksi' => 'IMPORT_CREATE',
                        'jenis_entitas' => 'InventoryUnit',
                        'entity_id' => $unit->id,
                        'old_value' => null,
                        'new_value' => ['kode_unit' => $unit->kode_unit, 'inventory_item_id' => $unit->inventory_item_id],
                    ]);
                    $created++;
                }
            }
        });

        return redirect()->route('inventory-units.index')->with('success', "{$created} unit fisik berhasil dibuat dari " . count($rows) . ' jenis barang. Kode unit dibuat otomatis.');
    }
}
