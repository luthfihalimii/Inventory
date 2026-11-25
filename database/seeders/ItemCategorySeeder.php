<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class ItemCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Audio & Recorder',
            'Baterai & Charger',
            'Kamera',
            'Lensa',
            'Lighting & Aksesori',
            'Media & Penyimpanan',
            'Rigging & Stabilizer',
            'Pakaian & Seragam',
            'Tripod & Penyangga',
        ];

        foreach ($categories as $category) {
            ItemCategory::updateOrCreate(
                ['nama' => $category],
                ['status_aktif' => true]
            );
        }
    }
}
