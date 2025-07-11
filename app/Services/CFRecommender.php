<?php

namespace App\Services;

use App\Models\UserClick;
use App\Models\Laptop;
use App\Models\Review;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CFRecommender
{
    public function getRecommendations($userId, $limit = null, $clicksByUser = null)
    {
        $logContext = ['type' => 'CF', 'user_id' => $userId];
        Log::channel('recommendations')->info("Memulai proses rekomendasi", $logContext);
        $clicksByUser = $clicksByUser ?? UserClick::all()->groupBy('user_id');
        $targetClicks = UserClick::where('user_id', $userId)
            ->pluck('click_count', 'brand_id')
            ->toArray();
        Log::channel('recommendations')->debug("Data klik user", $logContext + [
            'click_data' => $targetClicks,
            'total_brands' => count($targetClicks)
        ]);
        if (empty($targetClicks)) {
            Log::channel('recommendations')->warning("Tidak ada data klik", $logContext);
            return collect();
        }
        $similarities = [];
        $maxClick = max($targetClicks) ?: 1;
        foreach ($clicksByUser as $otherUserId => $clicks) {
            if ($otherUserId == $userId) continue;
            $otherClicks = $clicks->pluck('click_count', 'brand_id')->toArray();
            $similarity = $this->cosineSimilarity($targetClicks, $otherClicks);
            if ($similarity > 0.1) {
                $similarities[$otherUserId] = $similarity;
            }
        }
        $similarityStats = $this->calculateSimilarityStats($similarities);
        $maxSimilarity = $similarityStats['max'] ?: 1;
        Log::channel('recommendations')->debug("Hasil similarity", $logContext + [
            'total_similar_users' => count($similarities),
            'similarity_range' => $similarityStats
        ]);
        $maxClicksByUser = [];
        foreach ($clicksByUser as $otherUserId => $clicks) {
            $clicksArray = $clicks->pluck('click_count')->toArray();
            $maxClicksByUser[$otherUserId] = max($clicksArray) ?: 1;
        }
        $allLaptops = Laptop::with('brand')->get()->keyBy('id');
        $laptopScores = [];
        foreach ($allLaptops as $laptop) {
            $brandWeight = isset($targetClicks[$laptop->brand_id]) 
                ? ($targetClicks[$laptop->brand_id] / $maxClick) 
                : 0;
            $rating = $this->getLaptopRating($laptop->id);
            $ratingWeight = $rating / 5.0;
            $globalWeight = $this->getGlobalPopularity($laptop->id);
            $baseScore = ($brandWeight * 0.5) + ($ratingWeight * 0.25) + ($globalWeight * 0.25);
            $similarityBonus = 0;
            if (!empty($similarities)) {
                foreach ($similarities as $otherUserId => $similarity) {
                    $otherClicks = $clicksByUser[$otherUserId]->pluck('click_count', 'brand_id')->toArray();
                    if (isset($otherClicks[$laptop->brand_id])) {
                        $contribution = ($otherClicks[$laptop->brand_id] / $maxClicksByUser[$otherUserId])
                                      * ($similarity / $maxSimilarity);
                        $similarityBonus += ($contribution * 0.25);
                    }
                }
            }
            $laptopScores[$laptop->id] = min($baseScore + $similarityBonus, 1.0);
        }
        $maxScore = max($laptopScores) ?: 1;
        $normalizedScores = array_map(fn($score) => $score / $maxScore, $laptopScores);
        arsort($normalizedScores);
        $laptopIds = array_keys($normalizedScores);
        if ($limit !== null) {
            $laptopIds = array_slice($laptopIds, 0, $limit);
        }
        $recommendations = $allLaptops->whereIn('id', $laptopIds)
            ->map(function ($laptop) use ($normalizedScores) {
                $laptop->cf_score = $normalizedScores[$laptop->id] ?? 0;
                return $laptop;
            })
            ->sortByDesc('cf_score');
        $this->logRecommendationResults($logContext, $recommendations);
        return $recommendations->values();
    }

    private function getLaptopRating($laptopId): float
    {
        return Review::where('laptop_id', $laptopId)
            ->avg('rating') ?? 4.0;
    }

    private function getGlobalPopularity($laptopId): float
    {
        $reviewCount = Review::where('laptop_id', $laptopId)->count();
        $avgRating = Review::where('laptop_id', $laptopId)->avg('rating') ?? 4.0;
        $ratingComponent = ($avgRating / 5.0) * 0.6;
        $reviewComponent = 0.4 * log($reviewCount + 1) / log(50);
        return min($ratingComponent + $reviewComponent, 1.0);
    }

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

    private function logRecommendationResults(array $logContext, Collection $recommendations)
    {
        $scoreValues = $recommendations->pluck('cf_score')->toArray();
        $scoreStats = [
            'min' => count($scoreValues) ? min($scoreValues) : 0,
            'max' => count($scoreValues) ? max($scoreValues) : 0,
            'avg' => count($scoreValues) ? array_sum($scoreValues) / count($scoreValues) : 0
        ];
        $brandDistribution = [];
        foreach ($recommendations as $laptop) {
            $brandId = $laptop->brand_id;
            $brandDistribution[$brandId] = ($brandDistribution[$brandId] ?? 0) + 1;
        }
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

    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        $commonKeys = array_intersect(array_keys($vec1), array_keys($vec2));
        if (count($commonKeys) === 0) return 0;
        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;
        foreach ($commonKeys as $key) {
            $dotProduct += $vec1[$key] * $vec2[$key];
        }
        foreach ($vec1 as $val) {
            $magnitude1 += $val * $val;
        }
        foreach ($vec2 as $val) {
            $magnitude2 += $val * $val;
        }
        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);
        return ($magnitude1 && $magnitude2)
            ? $dotProduct / ($magnitude1 * $magnitude2)
            : 0;
    }
}