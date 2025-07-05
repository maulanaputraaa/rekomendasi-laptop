<?php

namespace App\Services;

use App\Models\Laptop;
use App\Models\Review;
use App\Models\UserClick;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

        // PERBAIKAN 1: Dapatkan preferensi fitur user dari brand yang diklik
        $featurePreferences = $this->getUserFeaturePreferences($brandClicks);

        // Ambil semua laptop yang relevan
        $laptops = Laptop::with('brand')
            ->withAvg('reviews', 'rating')
            ->get();

        // Hitung skor dengan fitur spesifik
        $scored = $laptops->map(function ($laptop) use ($brandWeights, $featurePreferences) {
            $brandWeight = $brandWeights[$laptop->brand_id] ?? 0;
            $rating = $laptop->reviews_avg_rating ?? 0;
            
            // PERBAIKAN 2: Hitung skor fitur
            $featureScore = $this->calculateFeatureScore($laptop, $featurePreferences);
            
            // PERBAIKAN 3: Formula baru dengan pembobotan fitur
            $cbfScore = round(
                ($brandWeight * 0.4) + 
                ($rating / 5 * 0.2) + 
                ($featureScore * 0.4), 
                4
            );
            
            $laptop->cbf_score = $cbfScore;
            $laptop->feature_score = $featureScore; // Untuk logging
            
            return $laptop;
        });

        // Log detail skor
        Log::channel('recommendations')->debug("Detail scoring", $logContext + [
            'sample_scores' => $scored->take(3)->map(function($laptop) use ($brandWeights) {
                $brandWeight = $brandWeights[$laptop->brand_id] ?? 0;
                $rating = $laptop->reviews_avg_rating ?? 0;
                
                return [
                    'laptop_id' => $laptop->id,
                    'brand_id' => $laptop->brand_id,
                    'brand_weight' => $brandWeight,
                    'rating' => $rating,
                    'feature_score' => $laptop->feature_score,
                    'cbf_score' => $laptop->cbf_score
                ];
            })->toArray()
        ]);

        // Urutkan dan ambil hasil
        $sorted = $scored->sortByDesc('cbf_score');
        if ($limit) {
            $sorted = $sorted->take($limit);
        }

        // Log hasil akhir
        $topRecommendations = $sorted->values();
        Log::channel('recommendations')->info("Hasil rekomendasi", $logContext + [
            'total_recommended' => $topRecommendations->count(),
            'top_3' => $topRecommendations->take(3)->map(function($laptop) {
                return [
                    'id' => $laptop->id,
                    'model' => $laptop->model,
                    'brand' => $laptop->brand->name,
                    'score' => $laptop->cbf_score
                ];
            })->toArray()
        ]);

        return $topRecommendations;
    }

    // PERBAIKAN 4: Dapatkan preferensi fitur user berdasarkan brand yang diklik
    private function getUserFeaturePreferences(Collection $brandClicks): array
    {
        $preferences = [
            'cpu' => [],
            'gpu' => [],
            'ram' => []
        ];

        // Hitung total klik untuk normalisasi
        $totalClicks = $brandClicks->sum('click_count');

        foreach ($brandClicks as $click) {
            // Dapatkan profil fitur brand (contoh implementasi)
            $brandFeatures = $this->getBrandFeatureProfile($click->brand_id);
            
            // Tambahkan ke preferensi dengan bobot klik
            foreach ($brandFeatures as $feature => $value) {
                if (!isset($preferences[$feature][$value])) {
                    $preferences[$feature][$value] = 0;
                }
                $preferences[$feature][$value] += $click->click_count / $totalClicks;
            }
        }

        // Ambil fitur dengan bobot tertinggi
        $finalPreferences = [];
        foreach ($preferences as $feature => $values) {
            arsort($values);
            $finalPreferences[$feature] = array_key_first($values);
        }

        return $finalPreferences;
    }

    // PERBAIKAN 5: Profil fitur per brand (contoh sederhana)
    private function getBrandFeatureProfile(int $brandId): array
    {
        // Contoh: Brand gaming memiliki CPU/GPU high-end
        $gamingBrands = [3, 5, 7]; // ASUS ROG, MSI, Alienware
        $officeBrands = [2, 4, 6]; // Lenovo ThinkPad, Dell Latitude, HP EliteBook
        
        if (in_array($brandId, $gamingBrands)) {
            return [
                'cpu' => 'high_end',
                'gpu' => 'dedicated',
                'ram' => 'large'
            ];
        } elseif (in_array($brandId, $officeBrands)) {
            return [
                'cpu' => 'mid_range',
                'gpu' => 'integrated',
                'ram' => 'medium'
            ];
        } else {
            return [
                'cpu' => 'balanced',
                'gpu' => 'balanced',
                'ram' => 'medium'
            ];
        }
    }

    // PERBAIKAN 6: Hitung skor fitur berdasarkan preferensi user
    private function calculateFeatureScore(Laptop $laptop, array $preferences): float
    {
        $score = 0;
        
        // 1. CPU Match
        $cpuType = $this->classifyCPU($laptop->cpu);
        if ($cpuType === $preferences['cpu']) {
            $score += 0.4;
        } elseif ($this->isCompatibleCPU($cpuType, $preferences['cpu'])) {
            $score += 0.2;
        }
        
        // 2. GPU Match
        $gpuType = $this->classifyGPU($laptop->gpu);
        if ($gpuType === $preferences['gpu']) {
            $score += 0.4;
        } elseif ($this->isCompatibleGPU($gpuType, $preferences['gpu'])) {
            $score += 0.2;
        }
        
        // 3. RAM Match
        $ramSize = $this->extractRAM($laptop->ram);
        $preferredRAM = $preferences['ram'] === 'large' ? 16 : 8;
        if ($ramSize >= $preferredRAM) {
            $score += 0.2;
        }

        return min($score, 1.0);
    }

    private function classifyCPU(string $cpu): string
    {
        $cpu = strtolower($cpu);
        
        if (str_contains($cpu, 'i9') || str_contains($cpu, 'ryzen 9') || str_contains($cpu, 'ryzen 7')) {
            return 'high_end';
        } elseif (str_contains($cpu, 'i7') || str_contains($cpu, 'ryzen 5')) {
            return 'mid_range';
        } elseif (str_contains($cpu, 'i5') || str_contains($cpu, 'ryzen 3')) {
            return 'balanced';
        } else {
            return 'entry_level';
        }
    }

    private function classifyGPU(string $gpu): string
    {
        $gpu = strtolower($gpu);
        
        if (str_contains($gpu, 'rtx') || str_contains($gpu, 'gtx') || str_contains($gpu, 'radeon rx')) {
            return 'dedicated';
        } else {
            return 'integrated';
        }
    }

    private function extractRAM(string $ram): int
    {
        preg_match('/(\d+)\s*GB/i', $ram, $matches);
        return isset($matches[1]) ? (int)$matches[1] : 8;
    }

    private function isCompatibleCPU(string $laptopCPU, string $preferredCPU): bool
    {
        // High-end bisa memenuhi mid-range, mid-range bisa memenuhi balanced
        $compatibility = [
            'high_end' => ['mid_range', 'balanced', 'entry_level'],
            'mid_range' => ['balanced', 'entry_level'],
            'balanced' => ['entry_level'],
            'entry_level' => []
        ];
        
        return in_array($preferredCPU, $compatibility[$laptopCPU] ?? []);
    }

    private function isCompatibleGPU(string $laptopGPU, string $preferredGPU): bool
    {
        // Dedicated bisa memenuhi integrated
        if ($preferredGPU === 'integrated' && $laptopGPU === 'dedicated') {
            return true;
        }
        return false;
    }
}