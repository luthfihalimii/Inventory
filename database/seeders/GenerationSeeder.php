<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GenerationSeeder extends Seeder
{
    public function run(): void
    {
        $generations = [
            'Gen 21',
            'Gen 20',
            'Gen 19',
        ];

        foreach ($generations as $generation) {
            DB::table('generations')->updateOrInsert(
                ['nama' => $generation],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
