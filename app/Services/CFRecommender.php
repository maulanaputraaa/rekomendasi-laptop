<?php

namespace App\Services;

use App\Models\UserClick;
use App\Models\Laptop;
use Illuminate\Support\Facades\Auth;

class CFRecommender
{
    public function getRecommendations($userId, $limit = null)
    {
        // Ambil semua data click per user
        $clicksByUser = UserClick::all()->groupBy('user_id');

        // Ambil click user yang sedang login
        $targetClicks = UserClick::where('user_id', $userId)->pluck('click_count', 'brand_id')->toArray();

        if (empty($targetClicks)) {
            return collect();
        }

        $similarities = [];

        // Hitung kesamaan dengan user lain berdasarkan pola klik
        foreach ($clicksByUser as $otherUserId => $clicks) {
            if ($otherUserId == $userId) continue;

            $otherClicks = $clicks->pluck('click_count', 'brand_id')->toArray();
            $similarity = $this->cosineSimilarity($targetClicks, $otherClicks);

            if ($similarity > 0) {
                $similarities[$otherUserId] = $similarity;
            }
        }

        // Ambil skor brand dari user sendiri (prioritas utama)
        $finalScores = $targetClicks;

        // Tambahkan skor dari user lain yang mirip
        foreach ($similarities as $otherUserId => $similarity) {
            $otherClicks = $clicksByUser[$otherUserId]->pluck('click_count', 'brand_id')->toArray();
            foreach ($otherClicks as $brandId => $count) {
                if (!isset($finalScores[$brandId])) {
                    $finalScores[$brandId] = 0;
                }
                $finalScores[$brandId] += $similarity * $count;
            }
        }

        // Urutkan skor tertinggi
        arsort($finalScores);
        $brandIds = array_keys(array_slice($finalScores, 0, $limit, true));

        // Ambil laptop dari brand-brand yang direkomendasikan
        $laptops = Laptop::whereIn('brand_id', $brandIds)->with('brand')->get();

        // Urutkan laptop berdasarkan urutan brand yang direkomendasikan
        $laptops = $laptops->sortBy(function ($laptop) use ($brandIds) {
            return array_search($laptop->brand_id, $brandIds);
        });

        return $laptops->values();
    }

    private function cosineSimilarity($vec1, $vec2)
    {
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
