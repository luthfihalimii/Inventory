<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            'Webmaster',
            'Reporter',
            'Videografer',
            'Fotografer',
            'Desain Grafis',
            'Copywriter',
            'Illustrator',
            'Perencanaan Konten',
        ];

        foreach ($divisions as $division) {
            DB::table('divisions')->updateOrInsert(
                ['nama' => $division],
                [
                    'status_aktif' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
