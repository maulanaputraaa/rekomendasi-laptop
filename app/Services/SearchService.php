<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Laptop;
use App\Services\HybridRecommender;
use App\Services\CBFRecommender;
use App\Services\CFRecommender;
use App\Services\TFIDFRecommender;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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

        // 1. Ambil hasil rekomendasi
        $hybridResults = $this->hybrid->getRecommendations($userId, null);
        $tfidfResults = $this->tfidf->recommend($query);

        // 2. Proses scoring
        $hybridScores = $this->assignHybridScores($hybridResults);
        $tfidfScores = $this->assignTFIDFScores($tfidfResults);

        // 3. Gabungkan dengan bobot 20% hybrid + 80% TF-IDF
        $combinedScores = [];
        foreach ($tfidfScores as $id => $score) {
            $combinedScores[$id] = ($score * 0.8) + (($hybridScores[$id] ?? 0) * 0.2);
        }
        
        // 4. Urutkan dan ambil 20 teratas
        arsort($combinedScores);
        $topIds = array_keys(array_slice($combinedScores, 0, 20, true));

        // 5. Query database dengan menjaga urutan
        $laptops = Laptop::with('brand')
            ->whereIn('id', $topIds)
            ->orderByRaw('FIELD(id, '.implode(',', $topIds).')')
            ->get()
            ->keyBy('id');

        // 6. Ambil rating
        $ratings = Review::whereIn('laptop_id', $topIds)
            ->selectRaw('laptop_id, AVG(rating) as avg_rating')
            ->groupBy('laptop_id')
            ->pluck('avg_rating', 'laptop_id');

        // 7. Bangun hasil akhir
        $result = collect();
        foreach ($topIds as $id) {
            if ($laptops->has($id)) {
                $laptop = $laptops[$id];
                $laptop->tfidf_score = $tfidfScores[$id] ?? 0;
                $laptop->average_rating = round($ratings[$id] ?? 0, 1);
                $result->push($laptop);
            }
        }

        // 8. Logging untuk debugging
        Log::channel('recommendations')->info('Search results', [
            'query' => $query,
            'top_ids' => $topIds,
            'tfidf_scores' => array_intersect_key($tfidfScores, array_flip($topIds)),
            'combined_scores' => array_intersect_key($combinedScores, array_flip($topIds))
        ]);

        return $result;
    }

    private function assignHybridScores(Collection $items): array
    {
        $count = $items->count();
        return $items->values()->mapWithKeys(function ($item, $i) use ($count) {
            return [$item->id => 1 - ($i / max($count - 1, 1))];
        })->all();
    }

    private function assignTFIDFScores(Collection $items): array
    {
        // Asumsi TFIDFRecommender mengembalikan collection dengan properti 'tfidf_score'
        $scores = $items->mapWithKeys(fn($item) => [$item->id => $item->tfidf_score])->all();
        
        // Normalisasi ke skala 0-1
        $maxScore = max($scores) ?: 1;
        return array_map(fn($score) => $score / $maxScore, $scores);
    }
}
