<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Laptop;
use App\Services\HybridRecommender;
use App\Services\CBFRecommender;
use App\Services\CFRecommender;
use App\Services\TFIDFRecommender;
use Illuminate\Support\Collection;

class SearchService
{
    protected $hybrid;
    protected $tfidf;

    public function __construct()
    {
        $cbf = new CBFRecommender();
        $cf = new CFRecommender();
        $this->hybrid = new HybridRecommender($cbf, $cf);
        $this->tfidf = new TFIDFRecommender();
    }

    public function searchWithTFIDF(string $query): Collection
    {
        $userId = \Illuminate\Support\Facades\Auth::user()->id ?? 1;

        // Coba deteksi rentang harga dari query
        $priceRange = $this->extractPriceRange($query);

        // Ambil rekomendasi dari hybrid dan TF-IDF
        $hybridResults = $this->hybrid->getRecommendations($userId, null);
        $tfidfResults = $this->tfidf->recommend($query);

        // Beri skor normalisasi dari ranking
        $hybridScores = $this->assignNormalizedScores($hybridResults);
        $tfidfScores = $this->assignNormalizedScores($tfidfResults);

        // Gabungkan skor dari hybrid dan tf-idf
        $combinedScores = [];
        foreach ($hybridScores as $id => $score) {
            $combinedScores[$id] = ($score * 0.3) + (($tfidfScores[$id] ?? 0) * 0.7);
        }
        foreach ($tfidfScores as $id => $score) {
            if (!isset($combinedScores[$id])) {
                $combinedScores[$id] = $score * 0.5;
            }
        }

        arsort($combinedScores); // Urutkan dari skor tertinggi
        $topIds = array_keys($combinedScores);

        // Ambil data laptop dan filter harga jika ada
        $laptopsQuery = Laptop::whereIn('id', $topIds);
        if ($priceRange) {
            $laptopsQuery->whereBetween('price', [$priceRange['min'], $priceRange['max']]);
        }

        $laptops = $laptopsQuery->get()->keyBy('id');

        // Ambil rating rata-rata
        $ratings = Review::whereIn('laptop_id', $laptops->keys())
            ->selectRaw('laptop_id, AVG(rating) as avg_rating')
            ->groupBy('laptop_id')
            ->pluck('avg_rating', 'laptop_id');

        // Susun hasil akhir dengan rating dan urutan skor
        $result = collect();
        foreach ($topIds as $id) {
            if (isset($laptops[$id])) {
                $laptop = $laptops[$id];
                $laptop->average_rating = round($ratings[$id] ?? 0, 1);
                $result->push($laptop);
            }
        }

        return $result;
    }

    private function assignNormalizedScores(Collection $items): array
    {
        $scores = [];
        $count = $items->count();
        foreach ($items->values() as $i => $item) {
            $id = $item->id;
            $scores[$id] = 1 - ($i / max($count - 1, 1)); // Nilai dari 1 ke 0
        }
        return $scores;
    }

    private function extractPriceRange(string $query): ?array
    {
        if (preg_match('/(\d{1,3})\s*(juta|jutaan)/i', $query, $matches)) {
            $angka = (int) $matches[1];
            $harga = $angka * 1_000_000;
            $toleransi = 1_000_000;

            return [
                'min' => max(0, $harga - $toleransi),
                'max' => $harga + $toleransi
            ];
        }

        return null;
    }
}
