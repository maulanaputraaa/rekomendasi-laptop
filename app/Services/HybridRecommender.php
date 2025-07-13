<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Models\Laptop;

/**
 * Layanan Rekomendasi Hybrid
 * 
 * Menggabungkan tiga strategi rekomendasi:
 * 1. Content-Based Filtering (CBF) - Rekomendasi berbasis konten
 * 2. Collaborative Filtering (CF) - Rekomendasi berbasis pengguna mirip
 * 3. TF-IDF - Rekomendasi berbasis pencarian
 * 
 * Strategi yang digunakan tergantung pada konteks:
 * - Jika ada query pencarian: Gunakan TF-IDF
 * - Jika tidak ada query: Gabungkan CBF dan CF dengan bobot
 */
class HybridRecommender
{
    /** @var CBFRecommender Layanan rekomendasi berbasis konten */
    protected $cbf;
    
    /** @var CFRecommender Layanan rekomendasi kolaboratif */
    protected $cf;
    
    /** @var TFIDFRecommender Layanan rekomendasi berbasis pencarian */
    protected $tfidf;

    /**
     * Konstruktor
     * 
     * @param CBFRecommender $cbf Instance CBFRecommender
     * @param CFRecommender $cf Instance CFRecommender
     * @param TFIDFRecommender $tfidf Instance TFIDFRecommender
     */
    public function __construct(
        CBFRecommender $cbf, 
        CFRecommender $cf,
        TFIDFRecommender $tfidf
    ) {
        $this->cbf = $cbf;
        $this->cf = $cf;
        $this->tfidf = $tfidf;
    }

    /**
     * Mendapatkan rekomendasi hybrid
     * 
     * Alur:
     * 1. Jika ada query pencarian, gunakan TF-IDF
     * 2. Jika tidak ada query, gabungkan CBF dan CF
     * 3. Atur bobot dinamis berdasarkan ketersediaan data
     * 
     * @param int $userId ID pengguna
     * @param string $query Query pencarian (opsional)
     * @param int $limit Jumlah maksimum rekomendasi
     * @return Collection Koleksi laptop yang direkomendasikan
     */
    public function getRecommendations($userId, $query = '', $limit = 10): Collection
    {
        // Prioritas 1: Jika ada query pencarian, gunakan TF-IDF
        if (!empty($query)) {
            return $this->tfidf->recommend($query)->take($limit);
        }
        
        // Prioritas 2: Gabungkan CBF dan CF
        $cbfRecs = $this->cbf->getRecommendations($userId, $limit);
        $cfRecs = $this->cf->getRecommendations($userId, $limit);
        
        // Atur bobot dinamis berdasarkan ketersediaan data
        $cbfWeight = $cbfRecs->isNotEmpty() ? 0.7 : 0;
        $cfWeight = $cfRecs->isNotEmpty() ? 0.3 : 0;
        
        // Jika kedua metode tidak tersedia, kembalikan koleksi kosong
        if ($cbfWeight == 0 && $cfWeight == 0) {
            return collect();
        }
        
        // Gabungkan dan kembalikan hasil
        return $this->combineRecommendations($cbfRecs, $cfRecs, $cbfWeight, $cfWeight)
            ->take($limit);
    }

    /**
     * Menggabungkan rekomendasi dari CBF dan CF dengan sistem bobot
     * 
     * Proses:
     * 1. Beri skor pada setiap laptop dari CBF
     * 2. Tambahkan skor laptop dari CF (jika ada)
     * 3. Urutkan berdasarkan skor gabungan tertinggi
     * 
     * @param Collection $cbfRecs Rekomendasi dari CBF
     * @param Collection $cfRecs Rekomendasi dari CF
     * @param float $cbfWeight Bobot untuk rekomendasi CBF (0-1)
     * @param float $cfWeight Bobot untuk rekomendasi CF (0-1)
     * @return Collection Koleksi laptop yang sudah digabungkan dan diurutkan
     */
    private function combineRecommendations(
        Collection $cbfRecs, 
        Collection $cfRecs,
        float $cbfWeight, 
        float $cfWeight
    ): Collection {
        // Koleksi untuk menyimpan hasil gabungan
        $combined = collect();
        
        // Proses rekomendasi CBF
        foreach ($cbfRecs as $laptop) {
            $score = ($laptop->cbf_score ?? $laptop->predicted_score ?? 0) * $cbfWeight;
            $combined->put($laptop->id, [
                'laptop' => $laptop,
                'score' => $score,
                'sources' => ['cbf']
            ]);
        }
        
        // Proses rekomendasi CF
        foreach ($cfRecs as $laptop) {
            $currentData = $combined->get($laptop->id);
            $cfScore = ($laptop->cf_score ?? $laptop->predicted_score ?? 0) * $cfWeight;
            
            if ($currentData) {
                // Jika laptop sudah ada dari CBF, tambahkan skor CF
                $newScore = $currentData['score'] + $cfScore;
                $sources = array_unique(array_merge($currentData['sources'], ['cf']));
                $combined->put($laptop->id, [
                    'laptop' => $laptop,
                    'score' => $newScore,
                    'sources' => $sources
                ]);
            } else {
                // Jika hanya dari CF
                $combined->put($laptop->id, [
                    'laptop' => $laptop,
                    'score' => $cfScore,
                    'sources' => ['cf']
                ]);
            }
        }
        
        // Urutkan berdasarkan skor tertinggi dan ekstrak laptop
        return $combined->sortByDesc('score')
            ->map(function ($item) {
                // Simpan informasi hybrid score untuk logging
                $item['laptop']->hybrid_score = $item['score'];
                $item['laptop']->hybrid_sources = implode('+', $item['sources']);
                return $item['laptop'];
            });
    }
}