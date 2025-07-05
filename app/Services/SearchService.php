<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Laptop;
use App\Models\UserClick;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SearchService
{
    protected $hybrid;
    protected $tfidf;

    public function __construct()
    {
        $cbf = new CBFRecommender();
        $cf = new CFRecommender();
        $tfidf = new TFIDFRecommender();
        $this->hybrid = new HybridRecommender($cbf, $cf, $tfidf);
        $this->tfidf = $tfidf;
    }

    public function searchWithTFIDF(string $query): Collection
    {
        // Ekstrak range harga dan bersihkan query
        $priceRange = $this->extractPriceRange($query);
        $cleanQuery = $this->removePriceTerms($query);
        $userId = Auth::id() ?? 1; // Gunakan user default jika tidak login

        // Ekstrak brand dari query jika ada
        $brandFilter = $this->extractBrandFilter($cleanQuery);
        $cleanQuery = $this->removeBrandTerms($cleanQuery);

        // Deteksi jenis query
        $isSpecificQuery = $this->isSpecificQuery($query);

        // Dapatkan rekomendasi TF-IDF dengan filter harga
        $tfidfResults = $this->tfidf->recommend(
            query: $cleanQuery,
            priceRange: $priceRange
        );
        $tfidfScores = $this->assignTFIDFScores($tfidfResults);

        // Logika kombinasi berdasarkan kondisi data user
        $strategy = 'TF-IDF only';
        $combinedScores = $tfidfScores;
        $componentScores = []; // Untuk menyimpan skor komponen

        if ($this->userHasClickData($userId)) {
            // Dapatkan skor CBF dan CF secara terpisah
            $cbfScores = $this->getCBFScores($userId);
            $cfScores = $this->getCFScores($userId);
            
            // Tentukan strategi berdasarkan jenis query
            if ($isSpecificQuery) {
                // Query spesifik: Prioritaskan TF-IDF
                $strategy = 'Hybrid (CBF+CF+TFIDF) [Specific]';
                $combinedScores = $this->combineThreeScores(
                    $cbfScores, 
                    $cfScores, 
                    $tfidfScores,
                    $brandFilter,
                    [
                        'cbf' => 0.2,
                        'cf' => 0.3,
                        'tfidf' => 0.5  // Bobot tinggi untuk TF-IDF
                    ],
                    $componentScores // Pass by reference untuk menyimpan detail skor
                );
            } else {
                // Query umum: Prioritaskan CF
                $strategy = 'Hybrid (CBF+CF+TFIDF) [General]';
                $combinedScores = $this->combineThreeScores(
                    $cbfScores, 
                    $cfScores, 
                    $tfidfScores,
                    $brandFilter,
                    [
                        'cbf' => 0.3,
                        'cf' => 0.5,  // Bobot tinggi untuk CF
                        'tfidf' => 0.2
                    ],
                    $componentScores
                );
            }
            
            // Jika CF tidak memberikan hasil, gunakan CBF + TF-IDF
            if (empty(array_filter($cfScores)) || count($cfScores) < 5) {
                $strategy = 'CBF + TF-IDF';
                $tfidfWeight = $isSpecificQuery ? 0.7 : 0.6;
                $combinedScores = $this->combineScoresWithQueryPriority(
                    $cbfScores, 
                    $tfidfScores,
                    $brandFilter,
                    $tfidfWeight,
                    $componentScores // Pass by reference untuk menyimpan detail skor
                );
            }
        }

        // Terapkan filter brand jika ada dalam query
        if ($brandFilter) {
            $combinedScores = $this->applyBrandFilter($combinedScores, $brandFilter);
        }

        // Filter skor rendah dan pastikan minimal 3 item
        $filteredScores = $this->filterLowScores($combinedScores);
        
        // Urutkan dan ambil 20 teratas
        arsort($filteredScores);
        $topIds = array_keys(array_slice($filteredScores, 0, 20, true));

        // Handle kasus tidak ada hasil
        if (empty($topIds)) {
            Log::channel('recommendations')->warning('No recommendations found', [
                'query' => $query,
                'clean_query' => $cleanQuery,
                'price_range' => $priceRange,
                'strategy' => $strategy,
                'brand_filter' => $brandFilter
            ]);
            return collect();
        }

        // Query database dengan menjaga urutan
        $laptops = Laptop::with('brand')
            ->whereIn('id', $topIds)
            ->get()
            ->keyBy('id');

        // Urutkan berdasarkan skor gabungan
        $orderedLaptops = collect();
        foreach ($topIds as $id) {
            if ($laptops->has($id)) {
                $orderedLaptops->push($laptops[$id]);
            }
        }

        // Ambil rating
        $ratings = Review::whereIn('laptop_id', $topIds)
            ->selectRaw('laptop_id, AVG(rating) as avg_rating')
            ->groupBy('laptop_id')
            ->pluck('avg_rating', 'laptop_id');

        // Bangun hasil akhir dengan skor
        $result = collect();
        foreach ($orderedLaptops as $laptop) {
            $laptop->tfidf_score = $tfidfScores[$laptop->id] ?? 0;
            $laptop->combined_score = $filteredScores[$laptop->id] ?? 0;
            $laptop->average_rating = round($ratings[$laptop->id] ?? 0, 1);
            $result->push($laptop);
        }

        // Logging untuk debugging
        Log::channel('recommendations')->info('Search results', [
            'query' => $query,
            'clean_query' => $cleanQuery,
            'price_range' => $priceRange,
            'strategy' => $strategy,
            'brand_filter' => $brandFilter,
            'top_ids' => $topIds,
            'tfidf_scores' => array_intersect_key($tfidfScores, array_flip($topIds)),
            'filtered_scores' => array_intersect_key($filteredScores, array_flip($topIds)),
            'original_scores' => array_intersect_key($combinedScores, array_flip($topIds))
        ]);

        // Log komponen skor untuk strategi hybrid
        if ($strategy === 'CBF + TF-IDF' || str_contains($strategy, 'Hybrid')) {
            $this->logComponentScores($topIds, $componentScores, $strategy);
        }

        return $result;
    }

    // Deteksi apakah query bersifat spesifik (mengandung istilah teknis)
    private function isSpecificQuery(string $query): bool
    {
        return preg_match('/(\d+gb\s+ram|rtx\s+\d+|ryzen\s+\d+|core\s+i\d+)/i', $query);
    }

    // Metode untuk filter skor rendah
    private function filterLowScores(array $scores): array
    {
        if (empty($scores)) {
            return [];
        }
        
        // Threshold statis (10%)
        $staticThreshold = 0.1;
        
        // Threshold adaptif (15% dari skor tertinggi)
        $maxScore = max($scores);
        $adaptiveThreshold = $maxScore * 0.15;
        
        // Gunakan threshold yang lebih tinggi
        $threshold = max($staticThreshold, $adaptiveThreshold);
        
        // Filter skor di bawah threshold
        $filtered = array_filter($scores, fn($score) => $score >= $threshold);
        
        // Jika hasil kurang dari 3, ambil 3 teratas dari skor asli
        if (count($filtered) < 3) {
            arsort($scores);
            $topItems = array_slice($scores, 0, 3, true);
            return $topItems + $filtered;
        }
        
        return $filtered;
    }

    private function extractPriceRange(string $query): ?array
    {
        // Format: "X juta" atau "X jutaan"
        if (preg_match('/(\d+)\s*(juta|jt|jutaan)/i', $query, $matches)) {
            $value = (int)$matches[1] * 1000000;
            return [$value - 1000000, $value + 1000000];
        }
        
        // Format: "X - Y juta"
        if (preg_match('/(\d+)\s*[-]?\s*(\d+)\s*(juta|jt)/i', $query, $matches)) {
            $min = (int)$matches[1] * 1000000;
            $max = (int)$matches[2] * 1000000;
            return [$min, $max];
        }
        
        return null;
    }

    private function extractBrandFilter(string $query): ?string
    {
        // Cari brand populer dalam query
        $brands = ['asus', 'acer', 'lenovo', 'hp', 'msi'];
        
        foreach ($brands as $brand) {
            if (stripos($query, $brand) !== false) {
                return $brand;
            }
        }
        
        return null;
    }

    private function removePriceTerms(string $query): string
    {
        // Hapus semua pola harga
        $clean = preg_replace([
            '/\d+\s*(rb|ribu|rban|k)/i',
            '/\d+\s*(juta|jt|jutaan|jtan)/i',
            '/\d+\s*[-]?\s*\d+\s*(juta|jt)/i',
            '/\s+an\s*/i'
        ], '', $query);
        
        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    private function removeBrandTerms(string $query): string
    {
        $brands = ['Lenovo', 'Hp', 'Asus', 'Acer', 'MSI'];
        return trim(str_ireplace($brands, '', $query));
    }

    private function assignHybridScores(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $count = $items->count();
        return $items->values()->mapWithKeys(function ($item, $i) use ($count) {
            return [$item->id => 1 - ($i / max($count - 1, 1))];
        })->all();
    }

    private function assignTFIDFScores(Collection $items): array
    {
        $scores = $items->mapWithKeys(fn($item) => [$item->id => $item->tfidf_score])->all();
        
        if (empty($scores)) {
            return [];
        }
        
        $maxScore = max($scores);
        return array_map(fn($score) => $score / ($maxScore ?: 1), $scores);
    }

    private function userHasClickData(int $userId): bool
    {
        return UserClick::where('user_id', $userId)->exists();
    }

    private function getCBFScores(int $userId): array
    {
        try {
            $cbf = new CBFRecommender();
            $cbfResults = $cbf->getRecommendations($userId);
            return $this->assignHybridScores($cbfResults);
        } catch (\Exception $e) {
            Log::error('Error getting CBF scores', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function getCFScores(int $userId): array
    {
        try {
            $cf = new CFRecommender();
            $cfResults = $cf->getRecommendations($userId);
            return $this->assignHybridScores($cfResults);
        } catch (\Exception $e) {
            Log::error('Error getting CF scores', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function combineThreeScores(
        array $cbfScores, 
        array $cfScores, 
        array $tfidfScores,
        ?string $brandFilter = null,
        array $weights = ['cbf' => 0.3, 'cf' => 0.4, 'tfidf' => 0.3],
        array &$componentScores = [] // Untuk menyimpan detail skor
    ): array {
        $combined = [];
        
        foreach ($tfidfScores as $id => $tfidfScore) {
            $cbfScore = $cbfScores[$id] ?? 0;
            $cfScore = $cfScores[$id] ?? 0;
            
            // Terapkan penalti jika brand tidak sesuai query
            $brandPenalty = 1.0;
            $penaltyReason = null;
            
            if ($brandFilter) {
                $laptopBrand = Laptop::find($id)->brand->name ?? '';
                if (stripos($laptopBrand, $brandFilter) === false) {
                    $brandPenalty = 0.3;
                    $penaltyReason = "Brand mismatch ($laptopBrand vs $brandFilter)";
                }
            }
            
            $combined[$id] = (
                ($cbfScore * $weights['cbf']) + 
                ($cfScore * $weights['cf']) + 
                ($tfidfScore * $weights['tfidf'])
            ) * $brandPenalty;
            
            // Simpan detail skor untuk logging
            $componentScores[$id] = [
                'cbf' => $cbfScore,
                'cf' => $cfScore,
                'tfidf' => $tfidfScore,
                'combined_raw' => $combined[$id],
                'weights' => $weights,
                'brand_penalty' => $brandPenalty,
                'penalty_reason' => $penaltyReason
            ];
        }
        
        return $combined;
    }

    private function combineScoresWithQueryPriority(
        array $personalScores, 
        array $tfidfScores,
        ?string $brandFilter = null,
        float $tfidfWeight = 0.6,
        array &$componentScores = [] // Untuk menyimpan detail skor
    ): array {
        $combined = [];
        $personalWeight = 1 - $tfidfWeight;
        
        foreach ($tfidfScores as $id => $tfidfScore) {
            $personalScore = $personalScores[$id] ?? 0;
            
            $brandPenalty = 1.0;
            $penaltyReason = null;
            
            if ($brandFilter) {
                $laptopBrand = Laptop::find($id)->brand->name ?? '';
                if (stripos($laptopBrand, $brandFilter) === false) {
                    $brandPenalty = 0.3;
                    $penaltyReason = "Brand mismatch ($laptopBrand vs $brandFilter)";
                }
            }
            
            $combined[$id] = (
                ($tfidfScore * $tfidfWeight) + 
                ($personalScore * $personalWeight)
            ) * $brandPenalty;
            
            // Simpan detail skor untuk logging
            $componentScores[$id] = [
                'cbf' => $personalScore, // Dalam konteks ini personalScores adalah CBF
                'tfidf' => $tfidfScore,
                'combined_raw' => $combined[$id],
                'weights' => [
                    'cbf' => $personalWeight,
                    'tfidf' => $tfidfWeight
                ],
                'brand_penalty' => $brandPenalty,
                'penalty_reason' => $penaltyReason
            ];
        }
        
        return $combined;
    }

    private function applyBrandFilter(array $scores, string $brand): array
    {
        $filtered = [];
        
        foreach ($scores as $id => $score) {
            $laptopBrand = Laptop::find($id)->brand->name ?? '';
            if (stripos($laptopBrand, $brand) !== false) {
                $filtered[$id] = $score * 1.2; // Beri bonus 20% untuk brand sesuai
            } else {
                continue; // Hapus item yang tidak sesuai
            }
        }
        
        return $filtered;
    }

    // Log detail komponen skor untuk laptop yang direkomendasikan
    private function logComponentScores(array $topIds, array $componentScores, string $strategy)
    {
        $filteredScores = [];
        
        foreach ($topIds as $id) {
            if (isset($componentScores[$id])) {
                $filteredScores[$id] = $componentScores[$id];
            }
        }
        
        Log::channel('recommendations')->info("Component scores for strategy: $strategy", [
            'top_ids' => $topIds,
            'component_scores' => $filteredScores,
            'score_details' => array_map(function($id) use ($componentScores) {
                return $componentScores[$id] ?? 'Score not calculated';
            }, $topIds)
        ]);
    }
}