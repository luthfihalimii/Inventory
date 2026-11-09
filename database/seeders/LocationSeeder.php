<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['code' => 'SEK', 'name' => 'Sekretariat ENT'],
            ['code' => 'GDG', 'name' => 'Gudang'],
        ];

        foreach ($locations as $location) {
            Location::updateOrCreate(['code' => $location['code']], $location);
        }
    }
}
