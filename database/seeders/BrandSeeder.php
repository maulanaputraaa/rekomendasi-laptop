<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'Asus'],
            ['name' => 'Acer'],
            ['name' => 'Lenovo'],
            ['name' => 'HP'],
            ['name' => 'MSI'],
        ];

        foreach ($brands as $brand) {
            Brand::create($brand);
        }
    }
}
