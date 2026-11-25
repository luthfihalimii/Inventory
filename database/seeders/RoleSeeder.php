<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'nama' => 'USER',
                'deskripsi' => 'Anggota ENT yang dapat mengajukan peminjaman dan pengembalian inventaris.',
            ],
            [
                'nama' => 'ADMIN',
                'deskripsi' => 'Pengelola inventaris, persetujuan peminjaman, verifikasi pengembalian, dan pengumuman.',
            ],
            [
                'nama' => 'SUPER_ADMIN',
                'deskripsi' => 'Pengelola anggota dan pengumuman serta pemantau seluruh transaksi dan audit log.',
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['nama' => $role['nama']],
                array_merge($role, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
