<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Models\Laptop;

class HybridRecommender
{
    protected $cbf;
    protected $cf;

    public function __construct(CBFRecommender $cbf, CFRecommender $cf)
    {
        $this->cbf = $cbf;
        $this->cf = $cf;
    }

    public function getRecommendations($userId, $query = '', $limit = null)
    {
        // Mendapatkan rekomendasi berdasarkan CBF dan CF
        $cbfRecs = $this->cbf->getRecommendations($userId, $limit);
        $cfRecs = $this->cf->getRecommendations($userId, $limit);

        // CBF lebih diutamakan jika ada banyak interaksi pengguna
        $cbfWeight = count($cbfRecs) > 0 ? 0.6 : 0.4;
        $cfWeight = count($cfRecs) > 0 ? 0.6 : 0.4;

        // Gabungkan hasil CBF dan CF
        $combined = $this->combineRecommendations($cbfRecs, $cfRecs, $cbfWeight, $cfWeight);

        // Saring berdasarkan query atau preferensi harga jika ada
        if ($query) {
            $filtered = $this->filterByQuery($combined, $query);
        } else {
            $filtered = $combined;
        }

        // Ambil hasil dengan limit yang ditentukan
        return $filtered->take($limit);
    }

    private function combineRecommendations(Collection $cbfRecs, Collection $cfRecs, float $cbfWeight, float $cfWeight): Collection
    {
        $scores = [];

        // Menggabungkan hasil berdasarkan ID dan skor
        $this->addScoresToRecommendations($cbfRecs, $scores, $cbfWeight);
        $this->addScoresToRecommendations($cfRecs, $scores, $cfWeight);

        // Urutkan berdasarkan skor total
        arsort($scores);

        // Ambil ID dari rekomendasi yang paling relevan
        $topIds = array_keys(array_slice($scores, 0, 10, true));

        // Ambil laptop berdasarkan ID
        return Laptop::whereIn('id', $topIds)->get()->keyBy('id');
    }

    private function addScoresToRecommendations(Collection $recs, array &$scores, float $weight)
    {
        foreach ($recs as $rec) {
            $score = $scores[$rec->id] ?? 0;
            $scores[$rec->id] = $score + $rec->predicted_score * $weight;
        }
    }

    private function filterByQuery(Collection $recs, string $query): Collection
    {
        // Misalnya filter berdasarkan harga dan kategori produk (contoh implementasi)
        $queryLower = strtolower($query);
        return $recs->filter(function ($laptop) use ($queryLower) {
            // Cek apakah query cocok dengan nama atau kategori
            return (strpos(strtolower($laptop->name), $queryLower) !== false) ||
                    (strpos(strtolower($laptop->category), $queryLower) !== false);
        });
    }
}
