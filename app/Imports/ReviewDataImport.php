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
        // Step 1: Grup data berdasarkan spesifikasi laptop dan hitung rata-rata harga
        $laptopGroups = [];
        $reviews = [];

        $rows->skip(1)->each(function ($row) use (&$laptopGroups, &$reviews) {
            $this->totalData++;

            // Pastikan row memiliki minimal 12 kolom
            if (count($row) < 12) {
                return;
            }

            [$tanggal, $responder, $brandName, $series, $model, $cpu, $ram, $storage, $gpu, $price, $rating, $review] = $row;

            // Validasi kolom penting tidak kosong
            if (
                empty($brandName) || empty($series) || empty($model) || empty($cpu) ||
                empty($ram) || empty($storage) || empty($gpu) || empty($price)
            ) {
                return;
            }

            $responderName = trim($responder);
            $reviewText = trim($review);

            // Buat key unik berdasarkan spesifikasi lengkap
            $laptopKey = trim($brandName) . '|' . trim($series) . '|' . trim($model) . '|' .
                trim($cpu) . '|' . (int)$ram . '|' . (int)$storage . '|' . trim($gpu);

            $currentPrice = (int) str_replace(['.', ','], '', $price);

            // Validasi harga harus lebih dari 0
            if ($currentPrice <= 0) {
                return;
            }

            // Grup laptop berdasarkan spesifikasi (SELALU DIPROSES untuk harga)
            if (!isset($laptopGroups[$laptopKey])) {
                $laptopGroups[$laptopKey] = [
                    'brandName' => trim($brandName),
                    'series' => trim($series),
                    'model' => trim($model),
                    'cpu' => trim($cpu),
                    'ram' => (int) $ram,
                    'storage' => (int) $storage,
                    'gpu' => trim($gpu),
                    'prices' => []
                ];
            }

            // Tambahkan harga ke grup (jika belum ada harga yang sama)
            if (!in_array($currentPrice, $laptopGroups[$laptopKey]['prices'])) {
                $laptopGroups[$laptopKey]['prices'][] = $currentPrice;
            }

            // Cek duplicate review HANYA untuk review, bukan untuk laptop
            $isDuplicateReview = Review::where('responder_name', $responderName)
                ->where('review', $reviewText)
                ->exists();

            if ($isDuplicateReview) {
                $this->duplicates++;
                // Review duplikat di-skip, tapi laptop tetap diproses
            } else {
                // Simpan review hanya jika tidak duplikat
                $reviews[] = [
                    'laptopKey' => $laptopKey,
                    'responder_name' => $responderName,
                    'rating' => (int) $rating,
                    'review' => $reviewText
                ];
            }
        });

        // Step 2: Proses setiap grup laptop dengan harga rata-rata
        foreach ($laptopGroups as $laptopKey => $laptopData) {
            $brand = Brand::firstOrCreate([
                'name' => $laptopData['brandName'],
            ]);

            // Cari laptop yang sudah ada berdasarkan spesifikasi lengkap
            $laptop = Laptop::where([
                'brand_id' => $brand->id,
                'series' => $laptopData['series'],
                'model' => $laptopData['model'],
                'cpu' => $laptopData['cpu'],
                'ram' => $laptopData['ram'],
                'storage' => $laptopData['storage'],
                'gpu' => $laptopData['gpu'],
            ])->first();

            // Hitung rata-rata harga dari file import ini
            $averagePrice = array_sum($laptopData['prices']) / count($laptopData['prices']);

            if ($laptop) {
                // Laptop sudah ada - OVERRIDE dengan harga rata-rata dari import ini
                $laptop->update(['price' => round($averagePrice)]);
            } else {
                // Laptop baru - buat dengan harga rata-rata
                $laptop = Laptop::create([
                    'brand_id' => $brand->id,
                    'series' => $laptopData['series'],
                    'model' => $laptopData['model'],
                    'cpu' => $laptopData['cpu'],
                    'ram' => $laptopData['ram'],
                    'storage' => $laptopData['storage'],
                    'gpu' => $laptopData['gpu'],
                    'price' => round($averagePrice),
                ]);
            }

            // Simpan semua review untuk laptop ini
            foreach ($reviews as $reviewData) {
                if ($reviewData['laptopKey'] === $laptopKey) {
                    Review::create([
                        'laptop_id' => $laptop->id,
                        'responder_name' => $reviewData['responder_name'],
                        'rating' => $reviewData['rating'],
                        'review' => $reviewData['review'],
                    ]);
                }
            }
        }
    }
}
