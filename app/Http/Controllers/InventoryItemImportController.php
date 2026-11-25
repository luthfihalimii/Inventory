<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Support\SpreadsheetRows;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryItemImportController extends Controller
{
    public function index(): View
    {
        return view('inventory-items.import');
    }

    public function template(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $extension = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';
        $filename = "template-import-jenis-inventaris.{$extension}";

        return response()->download(
            public_path("templates/{$filename}"),
            $filename,
            ['Content-Type' => $extension === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function store(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240']]);
        $isXlsx = strtolower($request->file('file')->getClientOriginalExtension()) === 'xlsx';

        if ($isXlsx) {
            try {
                $allRows = SpreadsheetRows::readXlsx($request->file('file')->getRealPath());
            } catch (\Throwable $e) {
                \Log::warning('Inventory import XLSX rejected', ['exception' => get_class($e)]);
                return back()->withErrors(['file' => 'File Excel tidak valid atau melewati batas pemrosesan. Periksa template dan ukuran file.']);
            }
            $header = array_shift($allRows);
            $dataRows = $allRows;
        } else {
            $handle = fopen($request->file('file')->getRealPath(), 'r');
            $header = fgetcsv($handle);
            $dataRows = [];
            while (($values = fgetcsv($handle)) !== false) $dataRows[] = $values;
            fclose($handle);
        }

        if (! $header) return back()->withErrors(['file' => 'File kosong atau tidak memiliki header.']);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $required = ['kode_barang', 'nama_barang', 'kategori', 'status', 'deskripsi'];
        if (array_diff($required, $header)) {
            return back()->withErrors(['file' => 'Header tidak sesuai. Gunakan kolom kode_barang, nama_barang, kategori, status, dan deskripsi.']);
        }
        if (count($dataRows) > 5000) return back()->withErrors(['file' => 'Maksimal 5.000 baris per proses import.']);

        $rows = [];
        $errors = [];
        $seen = [];
        $skipped = 0;

        foreach ($dataRows as $index => $values) {
            $line = $index + 2;
            if (! array_filter($values, fn ($value) => $value !== null && trim((string) $value) !== '')) continue;
            $row = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), null));
            foreach ($row as $key => $value) $row[$key] = is_string($value) ? trim($value) : $value;

            if (empty($row['kode_barang']) || empty($row['nama_barang']) || empty($row['kategori'])) {
                $errors[] = "Baris {$line}: kode_barang, nama_barang, dan kategori wajib diisi.";
                continue;
            }
            if (isset($seen[$row['kode_barang']]) || InventoryItem::where('kode_item', $row['kode_barang'])->exists()) {
                $skipped++;
                continue;
            }
            $seen[$row['kode_barang']] = true;

            if (! in_array($row['status'], ['ACTIVE', 'INACTIVE'], true)) {
                $errors[] = "Baris {$line}: status harus ACTIVE atau INACTIVE.";
                continue;
            }
            $category = ItemCategory::where('nama', $row['kategori'])->where('status_aktif', true)->first();
            if (! $category) {
                $errors[] = "Baris {$line}: kategori {$row['kategori']} tidak ditemukan atau tidak aktif.";
                continue;
            }

            $rows[] = [
                'kode_item' => $row['kode_barang'],
                'nama' => $row['nama_barang'],
                'kategori_id' => $category->id,
                'deskripsi' => $row['deskripsi'] ?: null,
                'status' => $row['status'],
            ];
        }

        if ($errors) return back()->withErrors(['file' => 'Import dibatalkan tanpa menyimpan data. ' . implode(' | ', array_slice($errors, 0, 10))]);
        if (! $rows) return back()->with('success', "Tidak ada data baru. {$skipped} duplikat dilewati.");

        DB::transaction(function () use ($rows) {
            foreach ($rows as $data) {
                $item = InventoryItem::create($data);
                AuditLog::create([
                    'actor_id' => auth()->id(),
                    'manager_term_id' => auth()->user()?->activeManagerTerm()?->id,
                    'aksi' => 'IMPORT_CREATE',
                    'jenis_entitas' => 'InventoryItem',
                    'entity_id' => $item->id,
                    'old_value' => null,
                    'new_value' => ['kode_item' => $item->kode_item, 'nama' => $item->nama, 'status' => $item->status],
                ]);
            }
        });

        return redirect()->route('inventory-items.index')->with('success', count($rows) . " jenis inventaris berhasil diimpor; {$skipped} duplikat dilewati.");
    }
}
