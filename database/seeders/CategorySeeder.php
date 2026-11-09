<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Kamera', 'description' => 'Kamera dan lensa'],
            ['name' => 'Audio', 'description' => 'Perekam suara dan mikrofon'],
            ['name' => 'Perlengkapan', 'description' => 'Tripod, lighting, dan aksesori'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['name' => $category['name']], $category);
        }
    }
}
