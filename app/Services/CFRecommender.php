<?php

namespace App\Services;

use App\Models\UserClick;
use App\Models\Laptop;
use Illuminate\Support\Facades\Log;

class CFRecommender
{
    public function getRecommendations($userId, $limit = null, $clicksByUser = null)
    {
        $logContext = ['type' => 'CF', 'user_id' => $userId];

        Log::channel('recommendations')->info("Memulai proses rekomendasi", $logContext);

        // Ambil semua data click per user
        $clicksByUser = $clicksByUser ?? UserClick::all()->groupBy('user_id');

        // Ambil click user target
        $targetClicks = UserClick::where('user_id', $userId)
            ->pluck('click_count', 'brand_id')
            ->toArray();

        // Log data klik user
        Log::channel('recommendations')->debug("Data klik user", $logContext + [
            'click_data' => $targetClicks,
            'total_brands' => count($targetClicks)
        ]);

        if (empty($targetClicks)) {
            Log::channel('recommendations')->warning("Tidak ada data klik", $logContext);
            return collect();
        }

        $similarities = [];

        // Hitung similarity dengan user lain
        foreach ($clicksByUser as $otherUserId => $clicks) {
            if ($otherUserId == $userId) continue;

            $otherClicks = $clicks->pluck('click_count', 'brand_id')->toArray();
            $similarity = $this->cosineSimilarity($targetClicks, $otherClicks);

            if ($similarity > 0) {
                $similarities[$otherUserId] = $similarity;
            }
        }

        // Log similarity
        Log::channel('recommendations')->debug("Hasil similarity", $logContext + [
            'total_similar_users' => count($similarities),
            'similarity_range' => [
                'min' => min($similarities),
                'max' => max($similarities),
                'avg' => array_sum($similarities) / count($similarities)
            ]
        ]);

        // Hitung skor akhir
        $finalScores = $targetClicks;
        foreach ($similarities as $otherUserId => $similarity) {
            $otherClicks = $clicksByUser[$otherUserId]->pluck('click_count', 'brand_id')->toArray();
            foreach ($otherClicks as $brandId => $count) {
                $finalScores[$brandId] = ($finalScores[$brandId] ?? 0) + ($similarity * $count);
            }
        }

        arsort($finalScores);
        $brandIds = array_keys(array_slice($finalScores, 0, $limit, true));

        // Ambil dan urutkan laptop
        $laptops = Laptop::whereIn('brand_id', $brandIds)
            ->with('brand')
            ->get()
            ->sortBy(function ($laptop) use ($brandIds) {
                return array_search($laptop->brand_id, $brandIds);
            });

        // Log hasil akhir
        Log::channel('recommendations')->info("Hasil rekomendasi", $logContext + [
            'recommendation_breakdown' => [
                'brands' => $brandIds,
                'laptops' => $laptops->map(function($laptop) {
                    return [
                        'id' => $laptop->id,
                        'brand' => $laptop->brand->name,
                        'model' => $laptop->model,
                        'specs' => [
                            'cpu' => $laptop->cpu,
                            'gpu' => $laptop->gpu,
                            'ram' => $laptop->ram
                        ]
                    ];
                })->toArray()
            ],
            'statistics' => [
                'total_laptops' => $laptops->count(),
                'brand_distribution' => array_count_values($laptops->pluck('brand_id')->toArray())
            ]
        ]);

        return $laptops->values();
    }

    private function cosineSimilarity($vec1, $vec2)
    {
        // Implementasi tetap sama
        $commonKeys = array_intersect(array_keys($vec1), array_keys($vec2));
        if (count($commonKeys) === 0) return 0;

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        foreach ($commonKeys as $key) {
            $dotProduct += $vec1[$key] * $vec2[$key];
        }

        foreach ($vec1 as $val) {
            $magnitude1 += $val * $val;
        }

        foreach ($vec2 as $val) {
            $magnitude2 += $val * $val;
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        return ($magnitude1 && $magnitude2) ? $dotProduct / ($magnitude1 * $magnitude2) : 0;
    }
}