<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Models\Laptop;

class HybridRecommender
{
    protected $cbf;
    protected $cf;
    protected $tfidf; // Tambahkan TFIDF Recommender

    public function __construct(
        CBFRecommender $cbf, 
        CFRecommender $cf,
        TFIDFRecommender $tfidf // Inject TFIDF Recommender
    ) {
        $this->cbf = $cbf;
        $this->cf = $cf;
        $this->tfidf = $tfidf; // Inisialisasi
    }

    public function getRecommendations($userId, $query = '', $limit = 10): Collection
    {
        // Jika ada query spesifik, utamakan TFIDF
        if (!empty($query)) {
            return $this->tfidf->recommend($query)->take($limit);
        }

        // Gabungkan CBF dan CF hanya ketika tidak ada query
        $cbfRecs = $this->cbf->getRecommendations($userId, '', $limit);
        $cfRecs = $this->cf->getRecommendations($userId, '', $limit);

        // Hitung bobot dinamis berdasarkan jumlah data
        $cbfWeight = $cbfRecs->isNotEmpty() ? 0.7 : 0;
        $cfWeight = $cfRecs->isNotEmpty() ? 0.3 : 0;

        // Gabungkan rekomendasi
        return $this->combineRecommendations($cbfRecs, $cfRecs, $cbfWeight, $cfWeight)
            ->take($limit);
    }

    private function combineRecommendations(
        Collection $cbfRecs, 
        Collection $cfRecs,
        float $cbfWeight, 
        float $cfWeight
    ): Collection {
        $combined = collect();
        
        // Proses CBF recommendations
        foreach ($cbfRecs as $laptop) {
            $score = ($laptop->predicted_score ?? 0) * $cbfWeight;
            $combined->put($laptop->id, [
                'laptop' => $laptop,
                'score' => $score,
                'type' => 'cbf'
            ]);
        }

        // Proses CF recommendations
        foreach ($cfRecs as $laptop) {
            $currentScore = $combined->get($laptop->id)['score'] ?? 0;
            $newScore = $currentScore + ($laptop->predicted_score ?? 0) * $cfWeight;
            
            $combined->put($laptop->id, [
                'laptop' => $laptop,
                'score' => $newScore,
                'type' => $currentScore > 0 ? 'hybrid' : 'cf'
            ]);
        }

        // Urutkan berdasarkan skor
        return $combined->sortByDesc('score')
            ->pluck('laptop');
    }
}