<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Laptop;
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
            $laptop = Laptop::firstOrCreate([
                'brand_id' => $brand->id,
                'series' => trim($series),
                'model' => trim($model),
            ], [
                'cpu' => trim($cpu),
                'ram' => (int) $ram,
                'storage' => (int) $storage,
                'gpu' => trim($gpu),
                'price' => (int) str_replace('.', '', $price),
            ]);
            Review::create([
                'laptop_id' => $laptop->id,
                'responder_name' => $responderName,
                'rating' => (int) $rating,
                'review' => $reviewText,
            ]);
        });
    }
}