<?php

namespace App\Services;

use App\Models\UserClick;
use App\Models\Laptop;
use App\Models\Review;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Layanan Rekomendasi Collaborative Filtering (CF)
 * 
 * Sistem ini merekomendasikan laptop berdasarkan:
 * 1. Kesamaan preferensi dengan pengguna lain
 * 2. Popularitas global laptop
 * 3. Rating dan ulasan laptop
 */
class CFRecommender
{
    /**
     * Mendapatkan rekomendasi laptop untuk pengguna tertentu
     * 
     * Alur kerja:
     * 1. Menghitung kesamaan preferensi dengan pengguna lain
     * 2. Menghitung skor CF untuk setiap laptop
     * 3. Menggabungkan faktor brand, rating, dan popularitas
     * 4. Mengurutkan laptop berdasarkan skor tertinggi
     * 
     * @param int $userId ID pengguna
     * @param int|null $limit Jumlah maksimum rekomendasi
     * @param Collection|null $clicksByUser Data klik semua user (opsional, untuk testing)
     * @return Collection Koleksi laptop yang direkomendasikan
     */
    public function getRecommendations($userId, $limit = null, $clicksByUser = null)
    {
        // Setup logging
        $logContext = ['type' => 'CF', 'user_id' => $userId];
        Log::channel('recommendations')->info("Memulai proses rekomendasi", $logContext);
        
        // Ambil data klik semua user jika tidak disediakan
        $clicksByUser = $clicksByUser ?? UserClick::all()->groupBy('user_id');
        
        // Ambil data klik user target
        $targetClicks = UserClick::where('user_id', $userId)
            ->pluck('click_count', 'brand_id')
            ->toArray();
        
        // Log data klik user target
        Log::channel('recommendations')->debug("Data klik user", $logContext + [
            'click_data' => $targetClicks,
            'total_brands' => count($targetClicks)
        ]);
        
        // Jika tidak ada data klik, kembalikan koleksi kosong
        if (empty($targetClicks)) {
            Log::channel('recommendations')->warning("Tidak ada data klik", $logContext);
            return collect();
        }
        
        // Hitung kesamaan dengan pengguna lain
        $similarities = $this->calculateUserSimilarities($targetClicks, $clicksByUser, $userId);
        $similarityStats = $this->calculateSimilarityStats($similarities);
        $maxSimilarity = $similarityStats['max'] ?: 1;
        
        Log::channel('recommendations')->debug("Hasil similarity", $logContext + [
            'total_similar_users' => count($similarities),
            'similarity_range' => $similarityStats
        ]);
        
        // Hitung maksimum klik per user
        $maxClicksByUser = $this->calculateMaxClicksPerUser($clicksByUser);
        
        // Ambil semua laptop
        $allLaptops = Laptop::with('brand')->get()->keyBy('id');
        
        // Hitung skor CF untuk setiap laptop
        $laptopScores = $this->calculateLaptopScores(
            $targetClicks, 
            $similarities,
            $maxClicksByUser,
            $maxSimilarity,
            $allLaptops
        );
        
        // Normalisasi skor
        $maxScore = max($laptopScores) ?: 1;
        $normalizedScores = array_map(fn($score) => $score / $maxScore, $laptopScores);
        arsort($normalizedScores);
        
        // Ambil laptop teratas
        $laptopIds = array_keys($normalizedScores);
        if ($limit !== null) {
            $laptopIds = array_slice($laptopIds, 0, $limit);
        }
        
        // Format hasil rekomendasi
        $recommendations = $allLaptops->whereIn('id', $laptopIds)
            ->map(function ($laptop) use ($normalizedScores) {
                $laptop->cf_score = $normalizedScores[$laptop->id] ?? 0;
                return $laptop;
            })
            ->sortByDesc('cf_score');
        
        // Log hasil rekomendasi
        $this->logRecommendationResults($logContext, $recommendations);
        
        return $recommendations->values();
    }

    /**
     * Menghitung kesamaan preferensi dengan pengguna lain menggunakan Cosine Similarity
     * 
     * @param array $targetClicks Data klik user target [brand_id => click_count]
     * @param Collection $clicksByUser Data klik semua user
     * @param int $userId ID user target
     * @return array Array [user_id => similarity_score]
     */
    private function calculateUserSimilarities(array $targetClicks, Collection $clicksByUser, int $userId): array
    {
        $similarities = [];
        $maxClick = max($targetClicks) ?: 1;
        
        foreach ($clicksByUser as $otherUserId => $clicks) {
            // Lewati user target
            if ($otherUserId == $userId) continue;
            
            $otherClicks = $clicks->pluck('click_count', 'brand_id')->toArray();
            $similarity = $this->cosineSimilarity($targetClicks, $otherClicks);
            
            // Hanya simpan kesamaan yang signifikan
            if ($similarity > 0.1) {
                $similarities[$otherUserId] = $similarity;
            }
        }
        
        return $similarities;
    }

    /**
     * Menghitung statistik kesamaan pengguna
     * 
     * @param array $similarities Array kesamaan [user_id => score]
     * @return array Statistik [min, max, avg]
     */
    private function calculateSimilarityStats(array $similarities): array
    {
        if (empty($similarities)) {
            return ['min' => 0, 'max' => 0, 'avg' => 0];
        }
        
        return [
            'min' => min($similarities),
            'max' => max($similarities),
            'avg' => array_sum($similarities) / count($similarities)
        ];
    }

    /**
     * Menghitung maksimum klik per user
     * 
     * @param Collection $clicksByUser Data klik semua user
     * @return array Array [user_id => max_click_count]
     */
    private function calculateMaxClicksPerUser(Collection $clicksByUser): array
    {
        $maxClicksByUser = [];
        
        foreach ($clicksByUser as $otherUserId => $clicks) {
            $clicksArray = $clicks->pluck('click_count')->toArray();
            $maxClicksByUser[$otherUserId] = max($clicksArray) ?: 1;
        }
        
        return $maxClicksByUser;
    }

    /**
     * Menghitung skor CF untuk setiap laptop
     * 
     * Formula skor:
     * Base Score = (50% brand) + (25% rating) + (25% popularitas)
     * Similarity Bonus = Kontribusi dari pengguna mirip (maks 25%)
     * 
     * @param array $targetClicks Data klik user target
     * @param array $similarities Kesamaan dengan pengguna lain
     * @param array $maxClicksByUser Maksimum klik per user
     * @param float $maxSimilarity Skor kesamaan maksimum
     * @param Collection $allLaptops Koleksi semua laptop
     * @return array Array [laptop_id => cf_score]
     */
    private function calculateLaptopScores(
        array $targetClicks,
        array $similarities,
        array $maxClicksByUser,
        float $maxSimilarity,
        Collection $allLaptops
    ): array {
        $maxClick = max($targetClicks) ?: 1;
        $laptopScores = [];
        
        foreach ($allLaptops as $laptop) {
            // Hitung bobot brand berdasarkan klik user
            $brandWeight = isset($targetClicks[$laptop->brand_id]) 
                ? ($targetClicks[$laptop->brand_id] / $maxClick) 
                : 0;
            
            // Hitung bobot rating laptop
            $rating = $this->getLaptopRating($laptop->id);
            $ratingWeight = $rating / 5.0;
            
            // Hitung popularitas global laptop
            $globalWeight = $this->getGlobalPopularity($laptop->id);
            
            // Base score (tanpa kontribusi pengguna mirip)
            $baseScore = ($brandWeight * 0.5) + ($ratingWeight * 0.25) + ($globalWeight * 0.25);
            
            // Hitung bonus dari pengguna mirip
            $similarityBonus = $this->calculateSimilarityBonus(
                $laptop->brand_id,
                $similarities,
                $maxClicksByUser,
                $maxSimilarity
            );
            
            // Gabungkan base score dan bonus
            $laptopScores[$laptop->id] = min($baseScore + $similarityBonus, 1.0);
        }
        
        return $laptopScores;
    }

    /**
     * Menghitung bonus kesamaan untuk sebuah brand
     * 
     * @param int $brandId ID brand laptop
     * @param array $similarities Kesamaan dengan pengguna lain
     * @param array $maxClicksByUser Maksimum klik per user
     * @param float $maxSimilarity Skor kesamaan maksimum
     * @return float Skor bonus kesamaan
     */
    private function calculateSimilarityBonus(
        int $brandId,
        array $similarities,
        array $maxClicksByUser,
        float $maxSimilarity
    ): float {
        $bonus = 0;
        
        foreach ($similarities as $otherUserId => $similarity) {
            // Jika user mirip pernah mengklik brand ini
            if (isset($maxClicksByUser[$otherUserId])) {
                $otherClicks = $maxClicksByUser[$otherUserId];
                
                // Hitung kontribusi user ini
                $contribution = ($otherClicks / $maxClicksByUser[$otherUserId])
                              * ($similarity / $maxSimilarity);
                
                $bonus += ($contribution * 0.25);
            }
        }
        
        return $bonus;
    }

    /**
     * Mendapatkan rating rata-rata laptop
     * 
     * @param int $laptopId ID laptop
     * @return float Rating (0-5)
     */
    private function getLaptopRating($laptopId): float
    {
        return Review::where('laptop_id', $laptopId)
            ->avg('rating') ?? 4.0; // Default 4.0 jika tidak ada rating
    }

    /**
     * Menghitung popularitas global laptop
     * 
     * Formula:
     * Popularitas = (60% rating) + (40% jumlah review)
     * 
     * @param int $laptopId ID laptop
     * @return float Skor popularitas (0-1)
     */
    private function getGlobalPopularity($laptopId): float
    {
        $reviewCount = Review::where('laptop_id', $laptopId)->count();
        $avgRating = Review::where('laptop_id', $laptopId)->avg('rating') ?? 4.0;
        
        // Komponen rating (60%)
        $ratingComponent = ($avgRating / 5.0) * 0.6;
        
        // Komponen jumlah review (40%)
        $reviewComponent = 0.4 * log($reviewCount + 1) / log(50);
        
        return min($ratingComponent + $reviewComponent, 1.0);
    }

    /**
     * Mencatat hasil rekomendasi ke log
     * 
     * @param array $logContext Konteks log [type, user_id]
     * @param Collection $recommendations Hasil rekomendasi
     */
    private function logRecommendationResults(array $logContext, Collection $recommendations)
    {
        $scoreValues = $recommendations->pluck('cf_score')->toArray();
        
        // Statistik skor
        $scoreStats = [
            'min' => count($scoreValues) ? min($scoreValues) : 0,
            'max' => count($scoreValues) ? max($scoreValues) : 0,
            'avg' => count($scoreValues) ? array_sum($scoreValues) / count($scoreValues) : 0
        ];
        
        // Distribusi brand
        $brandDistribution = [];
        foreach ($recommendations as $laptop) {
            $brandId = $laptop->brand_id;
            $brandDistribution[$brandId] = ($brandDistribution[$brandId] ?? 0) + 1;
        }
        
        // Contoh skor laptop
        $sampleScores = $recommendations->take(5)->map(function($laptop) {
            return [
                'id' => $laptop->id,
                'brand' => $laptop->brand->name,
                'model' => $laptop->model,
                'cf_score' => round($laptop->cf_score, 4),
                'specs' => [
                    'cpu' => $laptop->cpu,
                    'gpu' => $laptop->gpu,
                    'ram' => $laptop->ram
                ]
            ];
        });
        
        // Log informasi
        Log::channel('recommendations')->info("Hasil rekomendasi", $logContext + [
            'recommendation_breakdown' => [
                'sample_scores' => $sampleScores,
                'total_laptops' => $recommendations->count()
            ],
            'statistics' => [
                'total_laptops' => $recommendations->count(),
                'brand_distribution' => $brandDistribution,
                'score_range' => [
                    'min' => round($scoreStats['min'], 4),
                    'max' => round($scoreStats['max'], 4),
                    'avg' => round($scoreStats['avg'], 4)
                ]
            ]
        ]);
    }

    /**
     * Menghitung kesamaan kosinus antara dua vektor preferensi
     * 
     * @param array $vec1 Vektor preferensi user 1 [key => value]
     * @param array $vec2 Vektor preferensi user 2 [key => value]
     * @return float Skor kesamaan (0-1)
     */
    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        // Temukan brand yang sama-sama diklik
        $commonKeys = array_intersect(array_keys($vec1), array_keys($vec2));
        if (count($commonKeys) === 0) return 0;
        
        // Hitung dot product
        $dotProduct = 0;
        foreach ($commonKeys as $key) {
            $dotProduct += $vec1[$key] * $vec2[$key];
        }
        
        // Hitung magnitude vektor 1
        $magnitude1 = 0;
        foreach ($vec1 as $val) {
            $magnitude1 += $val * $val;
        }
        $magnitude1 = sqrt($magnitude1);
        
        // Hitung magnitude vektor 2
        $magnitude2 = 0;
        foreach ($vec2 as $val) {
            $magnitude2 += $val * $val;
        }
        $magnitude2 = sqrt($magnitude2);
        
        // Kembalikan kesamaan kosinus
        return ($magnitude1 && $magnitude2)
            ? $dotProduct / ($magnitude1 * $magnitude2)
            : 0;
    }
}