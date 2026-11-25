<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Division;
use App\Models\Generation;
use App\Models\Member;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Notifications\LoanRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberImportController extends Controller
{
    /**
     * Tahun kode untuk setiap generasi anggota.
     * Nomor urut berjalan terpisah untuk setiap tahun/generasi.
     */
    private const GENERATION_YEARS = [
        'Gen 19' => 2024,
        'Gen 20' => 2025,
        'Gen 21' => 2026,
    ];

    public function index(): View
    {
        return view('members.import');
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['nrp', 'nama', 'divisi', 'jabatan', 'generasi', 'password_awal']);
            fputcsv($out, ['3326600001', 'Nama Anggota', 'Webmaster', 'Tidak Ada', 'Gen 21', 'GANTI_PASSWORD_UNIK']);
            fclose($out);
        }, 'template-import-anggota-ent.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = $handle ? fgetcsv($handle) : false;

        if (!$header) {
            if ($handle) fclose($handle);
            return back()->withErrors(['file' => 'File CSV kosong atau tidak dapat dibaca.']);
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(fn ($value) => trim((string) $value), $header);

        $required = ['nrp', 'nama', 'divisi', 'jabatan', 'generasi', 'password_awal'];
        if ($header !== $required) {
            fclose($handle);
            return back()->withErrors([
                'file' => 'Header tidak sesuai. Gunakan urutan: ' . implode(',', $required) . '. Kolom kode dibuat otomatis oleh sistem.',
            ]);
        }

        $rows = [];
        $errors = [];
        $seenNrp = [];
        $skipped = 0;
        $line = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $line++;

            if ($line > 5001) {
                fclose($handle);
                return back()->withErrors(['file' => 'Maksimal 5.000 baris per proses import.']);
            }

            if (count(array_filter($values, fn ($value) => $value !== null && trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), null));
            foreach ($row as $key => $value) {
                $row[$key] = trim((string) $value);
            }

            if ($row['nrp'] === '' || $row['nama'] === '' || $row['divisi'] === '' ||
                $row['jabatan'] === '' || $row['generasi'] === '' || $row['password_awal'] === '') {
                $errors[] = "Baris {$line}: semua kolom wajib diisi.";
                continue;
            }

            if (isset($seenNrp[$row['nrp']])) {
                $skipped++;
                continue;
            }
            $seenNrp[$row['nrp']] = true;

            if (Member::where('nrp', $row['nrp'])->exists()) {
                $skipped++;
                continue;
            }

            if (!array_key_exists($row['generasi'], self::GENERATION_YEARS)) {
                $errors[] = "Baris {$line}: generasi tidak dikenali. Gunakan Gen 19, Gen 20, atau Gen 21.";
                continue;
            }

            $division = Division::where('nama', $row['divisi'])->where('status_aktif', true)->first();
            $position = Position::where('nama', $row['jabatan'])->first();
            $generation = Generation::where('nama', $row['generasi'])->first();

            if (!$division || !$position || !$generation) {
                $errors[] = "Baris {$line}: divisi, jabatan, atau generasi '{$row['generasi']}' tidak ditemukan pada master data.";
                continue;
            }

            $validator = Validator::make($row, [
                'nrp' => ['required', 'string', 'max:30'],
                'nama' => ['required', 'string', 'max:150'],
                'password_awal' => ['required', 'string', 'min:8'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Baris {$line}: " . implode(' ', $validator->errors()->all());
                continue;
            }

            $rows[] = [
                'nrp' => $row['nrp'],
                'nama' => $row['nama'],
                'divisi_id' => $division->id,
                'jabatan_id' => $position->id,
                'generasi_id' => $generation->id,
                'generasi' => $row['generasi'],
                'password_awal' => $row['password_awal'],
            ];
        }

        fclose($handle);

        if ($errors) {
            return back()->withErrors([
                'file' => 'Impor dibatalkan tanpa menyimpan data. ' . implode(' | ', array_slice($errors, 0, 12)),
            ]);
        }

        if (!$rows) {
            return back()->with('success', 'Tidak ada anggota baru. ' . $skipped . ' baris duplikat dilewati.');
        }

        $userRole = Role::where('nama', 'USER')->firstOrFail();

        DB::transaction(function () use ($rows, $userRole) {
            // Kunci record generasi untuk mencegah dua import bersamaan mengambil nomor yang sama.
            Generation::whereIn('nama', array_keys(self::GENERATION_YEARS))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $nextNumbers = [];
            foreach (self::GENERATION_YEARS as $generationName => $year) {
                $prefix = "ENT-{$year}-";
                $codes = Member::where('kode', 'like', $prefix . '%')->lockForUpdate()->pluck('kode');
                $max = 0;

                foreach ($codes as $code) {
                    if (preg_match('/^ENT-' . $year . '-(\d+)$/', $code, $matches)) {
                        $max = max($max, (int) $matches[1]);
                    }
                }

                $nextNumbers[$generationName] = $max + 1;
            }

            foreach ($rows as $row) {
                $year = self::GENERATION_YEARS[$row['generasi']];
                $sequence = $nextNumbers[$row['generasi']]++;
                $kode = sprintf('ENT-%d-%03d', $year, $sequence);

                // Cek ulang NRP dalam transaksi agar tidak membuat akun ganda.
                if (Member::where('nrp', $row['nrp'])->exists()) {
                    continue;
                }

                $member = Member::create([
                    'kode' => $kode,
                    'nrp' => $row['nrp'],
                    'nama' => $row['nama'],
                    'divisi_id' => $row['divisi_id'],
                    'jabatan_id' => $row['jabatan_id'],
                    'generasi_id' => $row['generasi_id'],
                    'status' => 'ACTIVE',
                ]);

                $account = User::create([
                    'member_id' => $member->id,
                    'username' => null,
                    'password' => Hash::make($row['password_awal']),
                    'status' => 'ACTIVE',
                    'must_change_password' => true,
                ]);

                $account->roles()->sync([$userRole->id]);
                $account->notify(new LoanRequestNotification(
                    'Ganti password awal',
                    'Password awal akun perlu diganti melalui Pengaturan Akun.',
                    'PASSWORD_CHANGE_REQUIRED'
                ));

                AuditLog::create([
                    'actor_id' => auth()->id(),
                    'manager_term_id' => auth()->user()?->activeManagerTerm()?->id,
                    'aksi' => 'IMPORT_CREATE',
                    'jenis_entitas' => 'Member',
                    'entity_id' => $member->id,
                    'old_value' => null,
                    'new_value' => [
                        'kode' => $member->kode,
                        'nrp' => $member->nrp,
                        'nama' => $member->nama,
                        'account_role' => 'USER',
                    ],
                ]);
            }
        });

        return redirect()->route('members.index')
            ->with('success', count($rows) . ' anggota berhasil diproses dan dibuatkan akun USER. ' . $skipped . ' baris duplikat dilewati.');
    }
}
