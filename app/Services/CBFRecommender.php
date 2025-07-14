<?php

namespace App\Services;

use App\Models\Laptop;
use App\Models\UserClick;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Layanan Rekomendasi Berbasis Konten (Content-Based Filtering)
 * 
 * Sistem ini merekomendasikan laptop berdasarkan:
 * 1. Riwayat klik brand pengguna
 * 2. Profil fitur laptop (CPU, GPU, RAM)
 * 3. Rating rata-rata laptop
 */
class CBFRecommender
{
    /**
     * Mendapatkan rekomendasi laptop untuk pengguna tertentu
     * 
     * Alur kerja:
     * 1. Mengambil riwayat klik brand pengguna
     * 2. Menghitung preferensi brand dan fitur
     * 3. Menghitung skor CBF untuk setiap laptop
     * 4. Mengurutkan laptop berdasarkan skor tertinggi
     * 
     * @param int $userId ID pengguna
     * @param int|null $limit Jumlah maksimum rekomendasi
     * @param Collection|null $brandClicks Data klik brand (opsional, untuk testing)
     * @return Collection Koleksi laptop yang direkomendasikan
     */
    public function getRecommendations($userId, $limit = null, $brandClicks = null)
    {
        $startTime = microtime(true);
        // Setup logging
        $logContext = [
            'type' => 'CBF', 
            'user_id' => $userId,
            'limit' => $limit,
            'timestamp' => now()->toISOString()
        ];
        Log::channel('recommendations')->info("🎯 Memulai Content-Based Filtering", $logContext);
        
        // Ambil data klik brand jika tidak disediakan
        $brandClicks = $brandClicks ?? UserClick::where('user_id', $userId)
            ->select('brand_id', 'click_count')
            ->orderByDesc('click_count')
            ->get();
        
        // Log data klik
        Log::channel('recommendations')->debug("Data klik user", $logContext + [
            'clicks' => $brandClicks->toArray(),
            'total_clicks' => $brandClicks->sum('click_count')
        ]);
        
        // Fallback jika tidak ada data klik
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
        
        // Hitung bobot brand berdasarkan klik
        $maxClicks = $brandClicks->first()->click_count;
        $brandWeights = $brandClicks->mapWithKeys(function ($item) use ($maxClicks) {
            $normalized = $item->click_count / $maxClicks;
            return [$item->brand_id => round($normalized, 2)];
        });
        
        Log::channel('recommendations')->debug("Bobot brand", $logContext + [
            'max_clicks' => $maxClicks,
            'brand_weights' => $brandWeights->toArray()
        ]);
        
        // Tentukan preferensi fitur pengguna
        $featurePreferences = $this->getUserFeaturePreferences($brandClicks);
        
        // Ambil semua laptop dengan rating
        $laptops = Laptop::with('brand')
            ->withAvg('reviews', 'rating')
            ->get();
        
        // Hitung skor CBF untuk setiap laptop
        $scored = $laptops->map(function ($laptop) use ($brandWeights, $featurePreferences) {
            $brandWeight = $brandWeights[$laptop->brand_id] ?? 0;
            $rating = $laptop->reviews_avg_rating ?? 0;
            $featureScore = $this->calculateFeatureScore($laptop, $featurePreferences);
            
            // Formula skor CBF:
            // 40% bobot brand + 20% rating + 40% kecocokan fitur
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
        
        // Log contoh skor
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
        
        // Urutkan berdasarkan skor tertinggi
        $sorted = $scored->sortByDesc('cbf_score');
        
        // Batasi jumlah hasil jika diperlukan
        if ($limit) {
            $sorted = $sorted->take($limit);
        }
        
        $topRecommendations = $sorted->values();
        
        $processingTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // Log hasil akhir
        Log::channel('recommendations')->info("✅ CBF rekomendasi selesai", $logContext + [
            'results_count' => $topRecommendations->count(),
            'processing_time_ms' => $processingTime,
            'user_preferences' => [
                'favorite_brands' => collect($brandWeights)->sortByDesc(fn($weight) => $weight)->take(3)->keys()->toArray(),
                'total_clicks' => $brandClicks->sum('click_count')
            ],
            'top_results' => $topRecommendations->take(3)->map(fn($laptop) => [
                'id' => $laptop->id,
                'name' => "{$laptop->brand->name} {$laptop->series} {$laptop->model}",
                'cbf_score' => round($laptop->cbf_score, 3),
                'feature_score' => round($laptop->feature_score ?? 0, 3),
                'price' => 'Rp ' . number_format($laptop->price)
            ])->toArray()
        ]);

        return $topRecommendations;
    }

    /**
     * Menentukan preferensi fitur pengguna berdasarkan riwayat klik brand
     * 
     * Fitur yang dianalisis:
     * - CPU: high_end, mid_range, balanced, entry_level
     * - GPU: dedicated, integrated
     * - RAM: large (≥16GB), medium (8-15GB)
     * 
     * @param Collection $brandClicks Koleksi klik brand
     * @return array Preferensi fitur [cpu, gpu, ram]
     */
    private function getUserFeaturePreferences(Collection $brandClicks): array
    {
        $preferences = [
            'cpu' => [],
            'gpu' => [],
            'ram' => []
        ];
        
        $totalClicks = $brandClicks->sum('click_count');
        
        // Akumulasi preferensi dari semua brand yang diklik
        foreach ($brandClicks as $click) {
            $brandFeatures = $this->getBrandFeatureProfile($click->brand_id);
            
            foreach ($brandFeatures as $feature => $value) {
                if (!isset($preferences[$feature][$value])) {
                    $preferences[$feature][$value] = 0;
                }
                // Tambahkan bobot proporsional berdasarkan jumlah klik
                $preferences[$feature][$value] += $click->click_count / $totalClicks;
            }
        }
        
        // Ambil nilai preferensi yang paling dominan
        $finalPreferences = [];
        foreach ($preferences as $feature => $values) {
            arsort($values);
            $finalPreferences[$feature] = array_key_first($values);
        }
        
        return $finalPreferences;
    }

    /**
     * Mendapatkan profil fitur default untuk sebuah brand
     * 
     * Kategori brand:
     * - Gaming: CPU high_end, GPU dedicated, RAM large
     * - Office: CPU mid_range, GPU integrated, RAM medium
     * - Lainnya: Fitur balanced
     * 
     * @param int $brandId ID brand
     * @return array Profil fitur [cpu, gpu, ram]
     */
    private function getBrandFeatureProfile(int $brandId): array
    {
        // ID brand gaming: ASUS ROG, MSI, dll
        $gamingBrands = [3, 5, 7];
        
        // ID brand office: Lenovo ThinkPad, Dell Latitude, dll
        $officeBrands = [2, 4, 6];
        
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

    /**
     * Menghitung skor kecocokan fitur laptop dengan preferensi pengguna
     * 
     * Bobot:
     * - Kecocokan CPU: 40%
     * - Kecocokan GPU: 40%
     * - Kecocokan RAM: 20%
     * 
     * @param Laptop $laptop Laptop yang dinilai
     * @param array $preferences Preferensi pengguna [cpu, gpu, ram]
     * @return float Skor fitur (0-1)
     */
    private function calculateFeatureScore(Laptop $laptop, array $preferences): float
    {
        $score = 0;
        
        // Klasifikasi fitur laptop
        $cpuType = $this->classifyCPU($laptop->cpu);
        $gpuType = $this->classifyGPU($laptop->gpu);
        $ramSize = $this->extractRAM($laptop->ram);
        
        // Penilaian CPU (40%)
        if ($cpuType === $preferences['cpu']) {
            $score += 0.4; // Kecocokan sempurna
        } elseif ($this->isCompatibleCPU($cpuType, $preferences['cpu'])) {
            $score += 0.2; // Kompatibel (lebih tinggi)
        }
        
        // Penilaian GPU (40%)
        if ($gpuType === $preferences['gpu']) {
            $score += 0.4; // Kecocokan sempurna
        } elseif ($this->isCompatibleGPU($gpuType, $preferences['gpu'])) {
            $score += 0.2; // Kompatibel (dedicated → integrated)
        }
        
        // Penilaian RAM (20%)
        $preferredRAM = $preferences['ram'] === 'large' ? 16 : 8;
        if ($ramSize >= $preferredRAM) {
            $score += 0.2; // Memenuhi atau melebihi preferensi
        }
        
        return min($score, 1.0); // Batasi maksimal 1.0
    }

    /**
     * Mengklasifikasikan tipe CPU berdasarkan spesifikasi
     * 
     * Kategori:
     * - high_end: Intel i9, Ryzen 9/7
     * - mid_range: Intel i7, Ryzen 5
     * - balanced: Intel i5, Ryzen 3
     * - entry_level: Lainnya
     * 
     * @param string $cpu Spesifikasi CPU
     * @return string Kategori CPU
     */
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

    /**
     * Mengklasifikasikan tipe GPU
     * 
     * @param string $gpu Spesifikasi GPU
     * @return string 'dedicated' atau 'integrated'
     */
    private function classifyGPU(string $gpu): string
    {
        $gpu = strtolower($gpu);
        
        if (str_contains($gpu, 'rtx') || 
            str_contains($gpu, 'gtx') || 
            str_contains($gpu, 'radeon rx')) {
            return 'dedicated';
        } else {
            return 'integrated';
        }
    }

    /**
     * Mengekstrak ukuran RAM dari spesifikasi
     * 
     * @param string $ram Spesifikasi RAM (contoh: "16GB DDR4")
     * @return int Ukuran RAM dalam GB
     */
    private function extractRAM(string $ram): int
    {
        preg_match('/(\d+)\s*GB/i', $ram, $matches);
        return isset($matches[1]) ? (int)$matches[1] : 8; // Default 8GB jika tidak ditemukan
    }

    /**
     * Mengecek kompatibilitas CPU laptop dengan preferensi
     * 
     * Aturan kompatibilitas:
     * - CPU high_end kompatibel dengan semua level di bawahnya
     * - CPU mid_range kompatibel dengan balanced dan entry_level
     * - CPU balanced kompatibel dengan entry_level
     * 
     * @param string $laptopCPU Kategori CPU laptop
     * @param string $preferredCPU Preferensi CPU pengguna
     * @return bool True jika kompatibel
     */
    private function isCompatibleCPU(string $laptopCPU, string $preferredCPU): bool
    {
        // Hierarki performa CPU
        $hierarchy = [
            'high_end' => ['mid_range', 'balanced', 'entry_level'],
            'mid_range' => ['balanced', 'entry_level'],
            'balanced' => ['entry_level'],
            'entry_level' => []
        ];
        
        // Periksa apakah preferensi ada di level yang lebih rendah
        return in_array($preferredCPU, $hierarchy[$laptopCPU] ?? []);
    }

    /**
     * Mengecek kompatibilitas GPU
     * 
     * Aturan:
     * - GPU dedicated dianggap kompatibel dengan preferensi integrated
     * - GPU integrated tidak kompatibel dengan preferensi dedicated
     * 
     * @param string $laptopGPU Tipe GPU laptop
     * @param string $preferredGPU Preferensi GPU pengguna
     * @return bool True jika kompatibel
     */
    private function isCompatibleGPU(string $laptopGPU, string $preferredGPU): bool
    {
        // Dedicated GPU bisa memenuhi kebutuhan integrated
        return ($preferredGPU === 'integrated' && $laptopGPU === 'dedicated');
    }
}