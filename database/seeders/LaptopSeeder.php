<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Laptop;

class LaptopSeeder extends Seeder
{
    public function run(): void
    {
        $dummyLaptops = [
            //Brand Asus
            ['brand_id' => 1, 'series' => 'ROG Strix', 'model' => 'G15', 'cpu' => 'AMD Ryzen 7 5800H', 'ram' => 16, 'storage' => '1024', 'gpu' => 'RTX 3060', 'price' => 18000000],
            ['brand_id' => 1, 'series' => 'ProArt StudioBook', 'model' => 'H7600', 'cpu' => 'Intel Core i7-12700H', 'ram' => 32, 'storage' => '2048', 'gpu' => 'RTX 3070', 'price' => 25000000],
            ['brand_id' => 1, 'series' => 'VivoBook', 'model' => 'X515', 'cpu' => 'Intel Core i5-1235U', 'ram' => 8, 'storage' => '512', 'gpu' => 'Intel Iris Xe', 'price' => 8500000],
            ['brand_id' => 1, 'series' => 'ExpertBook', 'model' => 'B9', 'cpu' => 'Intel Core i5-1135G7', 'ram' => 8, 'storage' => '512', 'gpu' => 'Intel UHD', 'price' => 12000000],

            //Brand Acer
            ['brand_id' => 2, 'series' => 'Predator Helios', 'model' => '300', 'cpu' => 'Intel Core i7-12700H', 'ram' => 16, 'storage' => '1024', 'gpu' => 'RTX 3060', 'price' => 17500000],
            ['brand_id' => 2, 'series' => 'ConceptD', 'model' => '7', 'cpu' => 'Intel Core i7-11800H', 'ram' => 32, 'storage' => '2048', 'gpu' => 'RTX 3080', 'price' => 32000000],
            ['brand_id' => 2, 'series' => 'Aspire', 'model' => '5', 'cpu' => 'AMD Ryzen 5 5500U', 'ram' => 8, 'storage' => '512', 'gpu' => 'Radeon Vega 7', 'price' => 7500000],
            ['brand_id' => 2, 'series' => 'TravelMate', 'model' => 'P6', 'cpu' => 'Intel Core i5-1135G7', 'ram' => 8, 'storage' => '512', 'gpu' => 'Intel Iris Xe', 'price' => 11000000],

            //Brand Lenovo
            ['brand_id' => 3, 'series' => 'Legion', 'model' => '5 Pro', 'cpu' => 'AMD Ryzen 7 6800H', 'ram' => 16, 'storage' => '1024', 'gpu' => 'RTX 3070', 'price' => 21000000],
            ['brand_id' => 3, 'series' => 'ThinkPad', 'model' => 'X1 Extreme', 'cpu' => 'Intel Core i7-11800H', 'ram' => 32, 'storage' => '2048', 'gpu' => 'RTX 3060', 'price' => 28000000],
            ['brand_id' => 3, 'series' => 'IdeaPad', 'model' => 'Slim 3', 'cpu' => 'AMD Ryzen 5 5500U', 'ram' => 8, 'storage' => '512', 'gpu' => 'Radeon Vega 7', 'price' => 8000000],
            ['brand_id' => 3, 'series' => 'ThinkBook', 'model' => '15', 'cpu' => 'Intel Core i5-1135G7', 'ram' => 8, 'storage' => '512', 'gpu' => 'Intel Iris Xe', 'price' => 10500000],

            //Brand HP
            ['brand_id' => 4, 'series' => 'Omen', 'model' => '15', 'cpu' => 'AMD Ryzen 7 5800H', 'ram' => 16, 'storage' => '1024', 'gpu' => 'RTX 3060', 'price' => 18500000],
            ['brand_id' => 4, 'series' => 'ZBook Fury', 'model' => '15G7', 'cpu' => 'Intel Xeon W-10885M', 'ram' => 64, 'storage' => '2048', 'gpu' => 'RTX 4000', 'price' => 45000000],
            ['brand_id' => 4, 'series' => 'Pavilion', 'model' => '14', 'cpu' => 'Intel Core i3-1115G4', 'ram' => 4, 'storage' => '256', 'gpu' => 'Intel UHD', 'price' => 6000000],
            ['brand_id' => 4, 'series' => 'ProBook', 'model' => '450 G8', 'cpu' => 'Intel Core i5-1135G7', 'ram' => 8, 'storage' => '512', 'gpu' => 'Intel Iris Xe', 'price' => 11500000],

            //Brand MSI
            ['brand_id' => 5, 'series' => 'GS66 Stealth', 'model' => '10UG', 'cpu' => 'Intel Core i9-11900H', 'ram' => 32, 'storage' => '2048', 'gpu' => 'RTX 3080', 'price' => 35000000],
            ['brand_id' => 5, 'series' => 'Creator', 'model' => '15', 'cpu' => 'Intel Core i7-11800H', 'ram' => 32, 'storage' => '2048', 'gpu' => 'RTX 3070', 'price' => 28000000],
            ['brand_id' => 5, 'series' => 'Modern', 'model' => '14', 'cpu' => 'Intel Core i5-1135G7', 'ram' => 8, 'storage' => '512', 'gpu' => 'Intel Iris Xe', 'price' => 12500000],
            ['brand_id' => 5, 'series' => 'Prestige', 'model' => '14', 'cpu' => 'Intel Core i7-1195G7', 'ram' => 16, 'storage' => '1024', 'gpu' => 'Intel Iris Xe', 'price' => 19500000],
        ];

        foreach ($dummyLaptops as $data) {
            Laptop::create($data);
        }
    }
}
