<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            'Ketua',
            'Wakil',
            'Sekretaris',
            'Sekretaris 1',
            'Sekretaris 2',
            'Bendahara',
            'Koordinator PSDM',
            'PSDM',
            'Koordinator LAB',
            'Asisten LAB',
            'Redaksi',
            'PIC Copywriter',
            'PIC Reporter',
            'PIC Fotografer',
            'PIC Videografer',
            'PIC Desain Grafis',
            'PIC Webmaster',
            'PIC Ilustrator',
            'PIC Perencanaan Konten',
            'Tidak Ada',
        ];

        foreach ($positions as $position) {
            DB::table('positions')->updateOrInsert(
                ['nama' => $position],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // Data anggota lama yang belum memiliki jabatan diberi posisi eksplisit.
        if (\Illuminate\Support\Facades\Schema::hasTable('members')) {
            $noPositionId = DB::table('positions')->where('nama', 'Tidak Ada')->value('id');
            DB::table('members')->whereNull('jabatan_id')->update([
                'jabatan_id' => $noPositionId,
                'updated_at' => now(),
            ]);
        }
    }
}
