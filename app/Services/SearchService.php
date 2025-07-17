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

    /**
     * Constructor untuk SearchService.
     * Menginisialisasi objek recommender hybrid (CBF, CF, TF-IDF).
     */
    public function __construct()
    {
        $cbf = new CBFRecommender();
        $cf = new CFRecommender();
        $tfidf = new TFIDFRecommender();
        $this->hybrid = new HybridRecommender($cbf, $cf, $tfidf);
        $this->tfidf = $tfidf;
    }

    /**
     * Melakukan pencarian laptop berdasarkan query menggunakan pendekatan TF-IDF dan hybrid recommendation.
     *
     * @param string $query Query pencarian dari user (contoh: "asus,kantor", "rog,rtx 4060")
     * @return Collection Koleksi laptop yang direkomendasikan
     */
    public function searchWithTFIDF(string $query): Collection
    {
        // Normalisasi query: ubah koma menjadi spasi
        $query = $this->normalizeQuery($query);
        
        $priceRange = $this->extractPriceRange($query);
        $cleanQuery = $this->removePriceTerms($query);
        $userId = Auth::id() ?? 1;
        $brandFilter = $this->extractBrandFilter($cleanQuery);
        $cleanQuery = $this->removeBrandTerms($cleanQuery);
        $isSpecificQuery = $this->isSpecificQuery($query);
        $isSpecificHardware = $this->isSpecificHardwareQuery($cleanQuery);
        
        // Dapatkan rekomendasi dari TF-IDF
        $tfidfResults = $this->tfidf->recommend(
            query: $cleanQuery,
            priceRange: $priceRange
        );
        
        $tfidfScores = $this->assignTFIDFScores($tfidfResults);
        $strategy = 'TF-IDF only';
        $combinedScores = $tfidfScores;
        $componentScores = [];
        
        // Gunakan hybrid recommendation jika user memiliki riwayat klik
        if ($this->userHasClickData($userId)) {
            $cbfScores = $this->getCBFScores($userId);
            $cfScores = $this->getCFScores($userId);
            
            if ($isSpecificQuery || $isSpecificHardware) {
                $strategy = 'Hybrid (CBF+CF+TFIDF) [Specific]';
                // Atur bobot berdasarkan jenis query dengan base weight baru
                $tfidfWeight = $isSpecificHardware ? 0.7 : 0.6;
                $weights = [
                    'cbf' => 0.3 - ($tfidfWeight - 0.5) * 0.4,
                    'cf' => 0.2 - ($tfidfWeight - 0.5) * 0.2,
                    'tfidf' => $tfidfWeight
                ];
                
                $combinedScores = $this->combineThreeScores(
                    $cbfScores,
                    $cfScores,
                    $tfidfScores,
                    $brandFilter,
                    $weights,
                    $componentScores
                );
            } else {
                $strategy = 'Hybrid (CBF+CF+TFIDF) [General]';
                $combinedScores = $this->combineThreeScores(
                    $cbfScores,
                    $cfScores,
                    $tfidfScores,
                    $brandFilter,
                    [
                        'tfidf' => 0.5,
                        'cbf' => 0.3,
                        'cf' => 0.2
                    ],
                    $componentScores
                );
            }
            
            // Fallback jika CF tidak memiliki cukup data
            if (empty(array_filter($cfScores)) || count($cfScores) < 5) {
                $strategy = 'CBF + TF-IDF';
                $tfidfWeight = ($isSpecificQuery || $isSpecificHardware) ? 0.7 : 0.6;
                $combinedScores = $this->combineScoresWithQueryPriority(
                    $cbfScores, 
                    $tfidfScores,
                    $brandFilter,
                    $tfidfWeight,
                    $componentScores
                );
            }
        }
        
        // Terapkan filter brand jika ada
        if ($brandFilter) {
            $combinedScores = $this->applyBrandFilter($combinedScores, $brandFilter);
        }
        
        // Filter skor rendah dan ambil top 20
        $filteredScores = $this->filterLowScores($combinedScores);
        arsort($filteredScores);
        $topIds = array_keys(array_slice($filteredScores, 0, 20, true));
        
        // Handle jika tidak ada rekomendasi
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
        
        // Dapatkan data laptop lengkap
        $laptops = Laptop::with('brand')
            ->whereIn('id', $topIds)
            ->get()
            ->keyBy('id');
        
        $orderedLaptops = collect();
        foreach ($topIds as $id) {
            if ($laptops->has($id)) {
                $orderedLaptops->push($laptops[$id]);
            }
        }
        
        // Hitung rating rata-rata
        $ratings = Review::whereIn('laptop_id', $topIds)
            ->selectRaw('laptop_id, AVG(rating) as avg_rating')
            ->groupBy('laptop_id')
            ->pluck('avg_rating', 'laptop_id');
        
        // Format hasil akhir
        $result = collect();
        foreach ($orderedLaptops as $laptop) {
            $laptop->tfidf_score = $tfidfScores[$laptop->id] ?? 0;
            $laptop->combined_score = $filteredScores[$laptop->id] ?? 0;
            $laptop->average_rating = round($ratings[$laptop->id] ?? 0, 1);
            
            // Tambahkan informasi harga
            $laptop->price_range = [
                'min' => $laptop->price,
                'max' => $laptop->price
            ];
            
            $result->push($laptop);
        }
        
        // Log hasil pencarian
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
        
        // Log skor komponen untuk analisis
        if ($strategy === 'CBF + TF-IDF' || str_contains($strategy, 'Hybrid')) {
            $this->logComponentScores($topIds, $componentScores, $strategy);
        }
        
        return $result;
    }

    /**
     * Normalisasi query dengan mengubah koma menjadi spasi.
     * Contoh: "asus,kantor" → "asus kantor"
     *
     * @param string $query Query asli dari user
     * @return string Query yang sudah dinormalisasi
     */
    private function normalizeQuery(string $query): string
    {
        $normalized = preg_replace('/,+/', ' ', $query);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return trim($normalized);
    }

    /**
     * Mengecek apakah query mengandung spesifikasi hardware spesifik.
     * Contoh: "rtx 4060", "ryzen 7", "16gb ram"
     *
     * @param string $query Query bersih (tanpa term harga/merek)
     * @return bool True jika query mengandung spesifikasi hardware
     */
    private function isSpecificHardwareQuery(string $query): bool
    {
        return preg_match('/(rtx\s*\d+|ryzen\s*\d+|core\s*i\d+|\d+gb\s*ram)/i', $query);
    }

    /**
     * Mengecek apakah query termasuk kategori spesifik.
     * Contoh: "8gb ram", "rtx 4060", "core i7"
     *
     * @param string $query Query asli dari user
     * @return bool True jika query termasuk kategori spesifik
     */
    private function isSpecificQuery(string $query): bool
    {
        return preg_match('/(\d+gb\s+ram|rtx\s+\d+|ryzen\s+\d+|core\s+i\d+)/i', $query);
    }

    /**
     * Menyaring skor rendah dengan threshold adaptif.
     * Mempertahankan minimal 3 item terbaik meskipun di bawah threshold.
     *
     * @param array $scores Array asosiatif [laptop_id => score]
     * @return array Skor yang sudah difilter
     */
    private function filterLowScores(array $scores): array
    {
        if (empty($scores)) {
            return [];
        }
        $staticThreshold = 0.1;
        $maxScore = max($scores);
        $adaptiveThreshold = $maxScore * 0.15;
        $threshold = max($staticThreshold, $adaptiveThreshold);
        $filtered = array_filter($scores, fn($score) => $score >= $threshold);
        if (count($filtered) < 3) {
            arsort($scores);
            $topItems = array_slice($scores, 0, 3, true);
            return $topItems + $filtered;
        }
        return $filtered;
    }

    /**
     * Mengekstrak rentang harga dari query.
     * Format yang didukung:
     * - "5 juta" → [4000000, 6000000]
     * - "4-6 juta" → [4000000, 6000000]
     *
     * @param string $query Query asli dari user
     * @return array|null Array [min, max] atau null jika tidak ditemukan
     */
    private function extractPriceRange(string $query): ?array
    {
        // Format dari filter: "harga:5000000-15000000"
        if (preg_match('/harga:(\d+)-(\d+)/', $query, $matches)) {
            return [(int)$matches[1], (int)$matches[2]];
        }
        
        // Format lama: "5 juta", "4-6 juta", dll
        if (preg_match('/(\d+)\s*(juta|jt|jutaan)/i', $query, $matches)) {
            $value = (int)$matches[1] * 1000000;
            return [$value - 1000000, $value + 1000000];
        }
        if (preg_match('/(\d+)\s*[-]?\s*(\d+)\s*(juta|jt)/i', $query, $matches)) {
            $min = (int)$matches[1] * 1000000;
            $max = (int)$matches[2] * 1000000;
            return [$min, $max];
        }
        return null;
    }

    /**
     * Mengekstrak filter merek dari query.
     * Merek yang didukung: Asus, Acer, Lenovo, HP, MSI.
     *
     * @param string $query Query asli dari user
     * @return string|null Nama merek atau null jika tidak ditemukan
     */
    private function extractBrandFilter(string $query): ?string
    {
        $brands = ['asus', 'acer', 'lenovo', 'hp', 'msi'];
        foreach ($brands as $brand) {
            if (stripos($query, $brand) !== false) {
                return $brand;
            }
        }
        return null;
    }

    /**
     * Menghapus term harga dari query.
     * Contoh: "laptop asus 5 juta" → "laptop asus"
     *
     * @param string $query Query asli dari user
     * @return string Query tanpa term harga
     */
    private function removePriceTerms(string $query): string
    {
        $clean = preg_replace([
            '/harga:\d+-\d+/i',  // Format filter harga
            '/\d+\s*(rb|ribu|rban|k)/i',
            '/\d+\s*(juta|jt|jutaan|jtan)/i',
            '/\d+\s*[-]?\s*\d+\s*(juta|jt)/i',
            '/\s+an\s*/i'
        ], '', $query);
        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    /**
     * Menghapus term merek dari query.
     * Contoh: "laptop asus murah" → "laptop murah"
     *
     * @param string $query Query asli dari user
     * @return string Query tanpa term merek
     */
    private function removeBrandTerms(string $query): string
    {
        $brands = ['Lenovo', 'Hp', 'Asus', 'Acer', 'MSI'];
        return trim(str_ireplace($brands, '', $query));
    }

    /**
     * Mengonversi hasil rekomendasi menjadi skor normalisasi.
     * Skor diubah menjadi rentang 0-1 berdasarkan peringkat.
     *
     * @param Collection $items Hasil rekomendasi
     * @return array Array asosiatif [laptop_id => score]
     */
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

    /**
     * Mengonversi hasil TF-IDF menjadi skor normalisasi.
     * Skor diubah menjadi rentang 0-1 berdasarkan nilai maksimal.
     *
     * @param Collection $items Hasil rekomendasi TF-IDF
     * @return array Array asosiatif [laptop_id => normalized_score]
     */
    private function assignTFIDFScores(Collection $items): array
    {
        $scores = $items->mapWithKeys(fn($item) => [$item->id => $item->tfidf_score])->all();
        if (empty($scores)) {
            return [];
        }
        $maxScore = max($scores);
        return array_map(fn($score) => $score / ($maxScore ?: 1), $scores);
    }

    /**
     * Mengecek apakah user memiliki riwayat klik.
     *
     * @param int $userId ID user
     * @return bool True jika user memiliki data klik
     */
    private function userHasClickData(int $userId): bool
    {
        return UserClick::where('user_id', $userId)->exists();
    }

    /**
     * Mendapatkan skor rekomendasi CBF (Content-Based Filtering).
     *
     * @param int $userId ID user
     * @return array Array asosiatif [laptop_id => score]
     */
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

    /**
     * Mendapatkan skor rekomendasi CF (Collaborative Filtering).
     *
     * @param int $userId ID user
     * @return array Array asosiatif [laptop_id => score]
     */
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

    /**
     * Mengombinasikan tiga jenis skor rekomendasi (CBF, CF, TF-IDF).
     * Menerapkan penalti jika merek tidak sesuai filter.
     *
     * @param array $cbfScores Skor CBF
     * @param array $cfScores Skor CF
     * @param array $tfidfScores Skor TF-IDF
     * @param string|null $brandFilter Filter merek
     * @param array $weights Bobot untuk masing-masing skor
     * @param array &$componentScores Referensi untuk menyimpan skor komponen
     * @return array Skor gabungan
     */
    private function combineThreeScores(
        array $cbfScores,
        array $cfScores,
        array $tfidfScores,
        ?string $brandFilter = null,
        array $weights = ['tfidf' => 0.5, 'cbf' => 0.3, 'cf' => 0.2],
        array &$componentScores = []
    ): array {
        $combined = [];
        foreach ($tfidfScores as $id => $tfidfScore) {
            $cbfScore = $cbfScores[$id] ?? 0;
            $cfScore = $cfScores[$id] ?? 0;
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

    /**
     * Mengombinasikan skor personalisasi (CBF) dengan skor query (TF-IDF).
     * Menerapkan penalti jika merek tidak sesuai filter.
     *
     * @param array $personalScores Skor personalisasi (CBF)
     * @param array $tfidfScores Skor TF-IDF
     * @param string|null $brandFilter Filter merek
     * @param float $tfidfWeight Bobot untuk TF-IDF
     * @param array &$componentScores Referensi untuk menyimpan skor komponen
     * @return array Skor gabungan
     */
    private function combineScoresWithQueryPriority(
        array $personalScores, 
        array $tfidfScores,
        ?string $brandFilter = null,
        float $tfidfWeight = 0.6,
        array &$componentScores = []
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
            $componentScores[$id] = [
                'cbf' => $personalScore,
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

    /**
     * Menerapkan filter merek pada skor.
     * Meningkatkan skor laptop dengan merek yang sesuai sebesar 20%.
     *
     * @param array $scores Array skor
     * @param string $brand Merek yang difilter
     * @return array Skor yang sudah difilter
     */
    private function applyBrandFilter(array $scores, string $brand): array
    {
        $filtered = [];
        foreach ($scores as $id => $score) {
            $laptopBrand = Laptop::find($id)->brand->name ?? '';
            if (stripos($laptopBrand, $brand) !== false) {
                $filtered[$id] = $score * 1.2;
            } else {
                continue;
            }
        }
        return $filtered;
    }

    /**
     * Mencatat skor komponen untuk analisis rekomendasi.
     *
     * @param array $topIds ID laptop teratas
     * @param array $componentScores Detail skor komponen
     * @param string $strategy Strategi yang digunakan
     */
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