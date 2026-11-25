<?php

namespace Database\Seeders;

use App\Models\LockerLocation;
use Illuminate\Database\Seeder;

class LockerLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            'Lemari Besi Hijau',
            'Lemari Besi Abu-abu',
            'Lemari Kaca 1',
            'Lemari Kaca 2',
        ];

        foreach ($locations as $location) {
            LockerLocation::updateOrCreate(
                ['nama' => $location],
                ['status_aktif' => true]
            );
        }
    }
}

