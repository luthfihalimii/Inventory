<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\ManagerTerm;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'ent:create-super-admin';

    protected $description = 'Membuat data anggota dan akun SUPER_ADMIN (Webmaster ENT) pertama.';

    public function handle(): int
    {
        $role = Role::where('nama', 'SUPER_ADMIN')->first();

        if (! $role) {
            $this->components->error(
                'Role SUPER_ADMIN belum tersedia. Jalankan php artisan db:seed terlebih dahulu.'
            );

            return self::FAILURE;
        }

        // Akun SUPER_ADMIN dipertahankan lintas masa jabatan.
        // Jangan membuat akun SUPER_ADMIN kedua.
        if (User::whereHas(
            'roles',
            fn($query) =>
            $query->where('roles.id', $role->id)
        )->exists()) {
            $this->components->error(
                'Akun SUPER_ADMIN sudah pernah dibuat. Gunakan proses pergantian pemegang akun, bukan membuat akun baru.'
            );

            return self::FAILURE;
        }

        // Divisi SUPER_ADMIN otomatis Webmaster.
        $division = Division::where('nama', 'Webmaster')
            ->where('status_aktif', true)
            ->first();

        if (! $division) {
            $this->components->error(
                'Divisi Webmaster belum tersedia atau tidak aktif. Periksa data divisi terlebih dahulu.'
            );

            return self::FAILURE;
        }

        $this->components->info(
            'Pembuatan akun SUPER_ADMIN'
        );

        $this->line(
            'Data anggota dan akun login akan dibuat secara terpisah dan dihubungkan.'
        );

        // Nama lengkap ditetapkan otomatis.
        $nama = 'Webmaster ENT';

        $username = trim(
            (string) $this->ask('Username login Super Admin', 'webmaster')
        );

        if (! preg_match('/^[A-Za-z][A-Za-z0-9._-]{2,59}$/', $username)) {
            $this->components->error(
                'Username harus diawali huruf, terdiri dari 3–60 karakter, dan hanya menggunakan huruf, angka, titik, garis bawah, atau tanda hubung.'
            );

            return self::FAILURE;
        }

        if (User::where('username', $username)->exists()) {
            $this->components->error(
                'Username tersebut sudah digunakan.'
            );

            return self::FAILURE;
        }

        $password = (string) $this->secret(
            'Password (minimal 8 karakter)'
        );

        $confirmation = (string) $this->secret(
            'Konfirmasi password'
        );

        if (strlen($password) < 8 || $password !== $confirmation) {
            $this->components->error(
                'Password minimal 8 karakter dan kedua input harus sama.'
            );

            return self::FAILURE;
        }

        $this->newLine();

        $this->table(['Data', 'Nilai'], [
            ['Nama', $nama],
            ['Kode', 'Tidak digunakan'],
            ['NRP', 'Tidak digunakan'],
            ['Divisi', $division->nama],
            ['Jabatan', 'Tidak ditetapkan'],
            ['Generasi', 'Tidak digunakan'],
            ['Role akun', 'SUPER_ADMIN'],
            ['Username awal', $username],
        ]);

        if (! $this->confirm(
            'Buat data anggota dan akun SUPER_ADMIN ini?',
            true
        )) {
            $this->components->warn(
                'Pembuatan akun dibatalkan.'
            );

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use (
                $role,
                $division,
                $nama,
                $username,
                $password
            ) {
                // Kunci baris role untuk mengurangi risiko
                // dua proses bootstrap berjalan bersamaan.
                Role::whereKey($role->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (User::whereHas(
                    'roles',
                    fn($query) =>
                    $query->where('roles.id', $role->id)
                )->exists()) {
                    throw ValidationException::withMessages([
                        'role' => 'Akun SUPER_ADMIN sudah tersedia.',
                    ]);
                }

                $member = Member::create([
                    'kode' => null,
                    'nrp' => null,
                    'nama' => $nama,
                    'divisi_id' => $division->id,
                    'jabatan_id' => null,
                    'generasi_id' => null,
                    'status' => 'ACTIVE',
                ]);

                $account = User::create([
                    'member_id' => $member->id,
                    'username' => $username,
                    'password' => $password,
                    'status' => 'ACTIVE',
                ]);

                $account->roles()->sync([$role->id]);

                ManagerTerm::create([
                    'user_id' => $account->id,
                    'member_id' => $member->id,
                    'role_id' => $role->id,
                    'started_at' => now(),
                    'ended_at' => null,
                    'notes' => 'Masa jabatan awal dibuat melalui perintah Artisan.',
                    'created_by' => null,
                ]);
            });
        } catch (\Throwable $exception) {
            report($exception);

            $this->components->error(
                'Gagal membuat akun SUPER_ADMIN: ' . $exception->getMessage()
            );

            return self::FAILURE;
        }

        $this->components->info(
            'Data anggota dan akun SUPER_ADMIN berhasil dibuat.'
        );

        $this->line(
            'Login menggunakan username yang baru dibuat.'
        );

        return self::SUCCESS;
    }
}
