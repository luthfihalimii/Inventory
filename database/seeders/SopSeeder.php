<?php

namespace Database\Seeders;

use App\Models\SopDocument;
use Illuminate\Database\Seeder;

class SopSeeder extends Seeder
{
    public function run(): void
    {
        SopDocument::updateOrCreate(
            ['version' => 1],
            [
                'title' => 'SOP Peminjaman Inventaris UKM Jurnalistik',
                'content' => implode("\n\n", [
                    '1. Pengajuan diajukan minimal H-2 untuk peminjaman terjadwal.',
                    '2. Pengajuan mendadak wajib menyertakan alasan dan tetap melalui approval.',
                    '3. Barang diambil dan dikembalikan sesuai jadwal yang disetujui.',
                    '4. Barang wajib dikembalikan dalam kondisi seperti saat diterima.',
                    '5. Kerusakan atau kehilangan wajib dilaporkan kepada Admin/Korlab.',
                    '6. Keterlambatan pengembalian dapat menyebabkan pemblokiran peminjaman.',
                ]),
                'effective_at' => now(),
                'is_active' => true,
            ],
        );
    }
}
