<?php

namespace App\Services;

use App\Models\Laptop;
use App\Models\Review;
use App\Models\UserClick;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CBFRecommender
{
    public function getRecommendations($userId, $limit = null, $brandClicks = null)
    {
        $logContext = ['type' => 'CBF', 'user_id' => $userId];

        Log::channel('recommendations')->info("Memulai proses rekomendasi", $logContext);

        // Ambil data klik user
        $brandClicks = $brandClicks ?? UserClick::where('user_id', $userId)
        ->select('brand_id', 'click_count')
        ->orderByDesc('click_count')
        ->get();

        // Log data klik user
        Log::channel('recommendations')->debug("Data klik user", $logContext + [
            'clicks' => $brandClicks->toArray(),
            'total_clicks' => $brandClicks->sum('click_count')
        ]);

        // Fallback jika tidak ada klik
        if ($brandClicks->isEmpty()) {
            Log::channel('recommendations')->warning("Menggunakan fallback rekomendasi", $logContext);
            
            $fallback = Laptop::with('brand')
                ->withAvg('reviews', 'rating')
                ->orderByDesc('reviews_avg_rating')
                ->take($limit)
                ->get();
            
            Log::channel('recommendations')->info("Hasil fallback", $logContext + [
                'recommended_ids' => $fallback->pluck('id')->toArray()
            ]);
            
            return $fallback;
        }

        // Hitung bobot brand
        $maxClicks = $brandClicks->first()->click_count;
        $brandWeights = $brandClicks->mapWithKeys(function ($item) use ($maxClicks) {
            $normalized = $item->click_count / $maxClicks;
            return [$item->brand_id => round($normalized, 2)];
        });

        // Log bobot brand
        Log::channel('recommendations')->debug("Bobot brand", $logContext + [
            'max_clicks' => $maxClicks,
            'brand_weights' => $brandWeights->toArray()
        ]);

        // Ambil laptop dari brand terkait
        $laptops = Laptop::with('brand')
            ->whereIn('brand_id', $brandWeights->keys())
            ->withAvg('reviews', 'rating')
            ->get();

        // Hitung skor
        $scored = $laptops->map(function ($laptop) use ($brandWeights) {
            $brandWeight = $brandWeights[$laptop->brand_id] ?? 0;
            $rating = $laptop->reviews_avg_rating ?? 0;
            $laptop->cbf_score = round(($brandWeight * 0.6) + ($rating / 5 * 0.4), 4);
            return $laptop;
        });

        // Log detail skor
        Log::channel('recommendations')->debug("Detail scoring", $logContext + [
            'sample_scores' => $scored->take(3)->map(function($laptop) {
                return [
                    'laptop_id' => $laptop->id,
                    'brand_id' => $laptop->brand_id,
                    'brand_weight' => $laptop->cbf_score / 0.6,
                    'rating' => $laptop->reviews_avg_rating,
                    'cbf_score' => $laptop->cbf_score
                ];
            })->toArray()
        ]);

        // Urutkan dan ambil hasil
        $sorted = $scored->sortByDesc('cbf_score')->take($limit)->values();

        // Log hasil akhir
        Log::channel('recommendations')->info("Hasil rekomendasi", $logContext + [
            'total_recommended' => $sorted->count(),
            'top_3' => $sorted->take(3)->map(function($laptop) {
                return [
                    'id' => $laptop->id,
                    'model' => $laptop->model,
                    'brand' => $laptop->brand->name,
                    'score' => $laptop->cbf_score
                ];
            })->toArray()
        ]);

        return $sorted;
    }
}