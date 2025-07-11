<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Models\Laptop;

class HybridRecommender
{
    protected $cbf;
    protected $cf;
    protected $tfidf;

    public function __construct(
        CBFRecommender $cbf, 
        CFRecommender $cf,
        TFIDFRecommender $tfidf
    ) {
        $this->cbf = $cbf;
        $this->cf = $cf;
        $this->tfidf = $tfidf;
    }

    public function getRecommendations($userId, $query = '', $limit = 10): Collection
    {
        if (!empty($query)) {
            return $this->tfidf->recommend($query)->take($limit);
        }
        $cbfRecs = $this->cbf->getRecommendations($userId, '', $limit);
        $cfRecs = $this->cf->getRecommendations($userId, '', $limit);
        $cbfWeight = $cbfRecs->isNotEmpty() ? 0.7 : 0;
        $cfWeight = $cfRecs->isNotEmpty() ? 0.3 : 0;
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
        foreach ($cbfRecs as $laptop) {
            $score = ($laptop->predicted_score ?? 0) * $cbfWeight;
            $combined->put($laptop->id, [
                'laptop' => $laptop,
                'score' => $score,
                'type' => 'cbf'
            ]);
        }
        foreach ($cfRecs as $laptop) {
            $currentScore = $combined->get($laptop->id)['score'] ?? 0;
            $newScore = $currentScore + ($laptop->predicted_score ?? 0) * $cfWeight;
            $combined->put($laptop->id, [
                'laptop' => $laptop,
                'score' => $newScore,
                'type' => $currentScore > 0 ? 'hybrid' : 'cf'
            ]);
        }
        return $combined->sortByDesc('score')
            ->pluck('laptop');
    }
}