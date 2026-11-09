<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            ['code' => 'RED', 'name' => 'Redaksi'],
            ['code' => 'FOTO', 'name' => 'Fotografi'],
            ['code' => 'MULTI', 'name' => 'Multimedia'],
        ];

        foreach ($divisions as $division) {
            Division::updateOrCreate(['code' => $division['code']], $division);
        }
    }
}
