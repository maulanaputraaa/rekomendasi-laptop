<?php

namespace App\Services;

use App\Models\Laptop;
use App\Models\Review;
use App\Models\UserClick;
use Illuminate\Support\Facades\DB;

class CBFRecommender
{
    public function getRecommendations($userId, $limit = null)
    {
        // Ambil data klik user terhadap brand
        $brandClicks = UserClick::where('user_id', $userId)
            ->select('brand_id', 'click_count')
            ->orderByDesc('click_count')
            ->get();

        // Jika user belum klik apapun, fallback ke laptop random
        if ($brandClicks->isEmpty()) {
            return Laptop::with('brand')
                ->withAvg('reviews', 'rating')
                ->orderByDesc('reviews_avg_rating')
                ->take($limit)
                ->get();
        }

        // Normalisasi klik menjadi bobot preferensi (misal: 1.0, 0.8, 0.6, ...)
        $maxClicks = $brandClicks->first()->click_count;
        $brandWeights = $brandClicks->mapWithKeys(function ($item) use ($maxClicks) {
            $normalized = $item->click_count / $maxClicks;
            return [$item->brand_id => $normalized];
        });

        // Ambil semua laptop dari brand yang disukai user
        $laptops = Laptop::with('brand')
            ->whereIn('brand_id', $brandWeights->keys())
            ->withAvg('reviews', 'rating')
            ->get();

        // Hitung skor kombinasi: preferensi brand + rating
        $scored = $laptops->map(function ($laptop) use ($brandWeights) {
            $brandWeight = $brandWeights[$laptop->brand_id] ?? 0;
            $rating = $laptop->reviews_avg_rating ?? 0;
            // Kombinasi bobot brand dan rating (dengan bobot 0.6:0.4)
            $laptop->cbf_score = round(($brandWeight * 0.6) + ($rating / 5 * 0.4), 4);
            return $laptop;
        });

        // Urutkan berdasarkan skor
        $sorted = $scored->sortByDesc('cbf_score')->take($limit)->values();

        return $sorted;
    }
}
