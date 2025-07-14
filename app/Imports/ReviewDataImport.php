<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Laptop;
use App\Models\LaptopPrice;
use App\Models\Review;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class ReviewDataImport implements ToCollection
{
    public $duplicates = 0;
    public $totalData = 0;

    public function collection(Collection $rows)
    {
        $rows->skip(1)->each(function ($row) {
            $this->totalData++;
            [$tanggal, $responder, $brandName, $series, $model, $cpu, $ram, $storage, $gpu, $price, $rating, $review] = $row;
            $responderName = trim($responder);
            $reviewText = trim($review);
            if (Review::where('responder_name', $responderName)
                ->where('review', $reviewText)
                ->exists()) {
                $this->duplicates++;
                return;
            }
            $brand = Brand::firstOrCreate([
                'name' => trim($brandName),
            ]);
            // Cari laptop yang sudah ada atau buat baru
            $laptop = Laptop::where([
                'brand_id' => $brand->id,
                'series' => trim($series),
                'model' => trim($model),
            ])->first();

            $currentPrice = (int) str_replace('.', '', $price);
            
            if ($laptop) {
                // Update spesifikasi laptop jika berbeda
                $laptop->update([
                    'cpu' => trim($cpu),
                    'ram' => (int) $ram,
                    'storage' => (int) $storage,
                    'gpu' => trim($gpu),
                ]);

                // Simpan harga baru ke tabel laptop_prices jika belum ada
                $existingPrice = LaptopPrice::where([
                    'laptop_id' => $laptop->id,
                    'price' => $currentPrice,
                    'source' => 'import'
                ])->exists();

                if (!$existingPrice) {
                    LaptopPrice::create([
                        'laptop_id' => $laptop->id,
                        'price' => $currentPrice,
                        'source' => 'import'
                    ]);
                }

                // Update harga rata-rata di tabel laptops
                $averagePrice = $laptop->laptopPrices()->avg('price');
                $laptop->update(['price' => round($averagePrice, 2)]);
            } else {
                // Jika laptop belum ada, buat baru
                $laptop = Laptop::create([
                    'brand_id' => $brand->id,
                    'series' => trim($series),
                    'model' => trim($model),
                    'cpu' => trim($cpu),
                    'ram' => (int) $ram,
                    'storage' => (int) $storage,
                    'gpu' => trim($gpu),
                    'price' => $currentPrice,
                ]);

                // Simpan harga pertama ke tabel laptop_prices
                LaptopPrice::create([
                    'laptop_id' => $laptop->id,
                    'price' => $currentPrice,
                    'source' => 'import'
                ]);
            }
            Review::create([
                'laptop_id' => $laptop->id,
                'responder_name' => $responderName,
                'rating' => (int) $rating,
                'review' => $reviewText,
            ]);
        });
    }
}