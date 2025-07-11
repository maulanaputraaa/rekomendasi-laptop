<?php

namespace App\Services;

use App\Models\Laptop;
use Illuminate\Support\Collection;
use Sastrawi\Stemmer\StemmerFactory;
use Illuminate\Support\Facades\Log;

class TFIDFRecommender
{
    private $stopWords;
    private $synonymMapping;

    public function __construct()
    {
        $this->stopWords = $this->getStopWords();
        $this->synonymMapping = $this->getSynonymMapping();
    }
    public function recommend(string $query, ?array $priceRange = null): Collection
    {
        $contextWeights = $this->detectQueryContext($query);
        if ($priceRange) {
            $contextWeights['price_range'] = $priceRange;
            $contextWeights['price'] = 5;
        }
        $laptops = Laptop::with('reviews', 'brand')->get();
        $allDocsForIDF = collect();
        $laptopDocs = collect();
        foreach ($laptops as $laptop) {
            $specsText = $this->combineSpecs($laptop);
            $specsTerms = $this->tokenize($specsText, 3);
            $allDocsForIDF->push($specsTerms);
            $reviewTerms = [];
            foreach ($laptop->reviews as $review) {
                $reviewText = strtolower($review->review);
                if ($this->isNegativeSentence($reviewText)) continue;
                if (!$this->isPositiveSentence($reviewText)) continue;
                if ($this->reviewMismatchWithSpecs($reviewText, $laptop)) {
                    Log::channel('recommendations')->info('Review dibuang karena mismatch', [
                        'laptop_id' => $laptop->id,
                        'review' => $review->review,
                    ]);
                    continue;
                }
                $revTerms = $this->tokenize($review->review);
                $allDocsForIDF->push($revTerms);
                $reviewTerms = array_merge($reviewTerms, $revTerms);
            }
            $laptopDocs[$laptop->id] = array_merge($specsTerms, $reviewTerms);
        }
        $idf = $this->calculateIDF($allDocsForIDF);
        $queryTerms = $this->tokenize($query);
        $scores = $this->calculateScores($laptopDocs, $queryTerms, $idf, $contextWeights);
        return $this->getSortedResults($scores, $contextWeights)
            ->map(function ($laptop) use ($scores) {
                $laptop->tfidf_score = $scores[$laptop->id] ?? 0;
                return $laptop;
            });
    }

    private function detectQueryContext(string $query): array
    {
        $weights = [
            'cpu' => 1,
            'gpu' => 1,
            'ram' => 1,
            'storage' => 1,
            'price' => 1,
            'cooling' => 1,
            'display' => 1,
            'kantor' => 0,
            'gaming' => 0,
            'editing' => 0,
            'sekolah' => 0,
        ];
        $query = strtolower($query);
        if (preg_match('/\b(kantor|office|pekerjaan|bisnis)\b/i', $query)) {
            $weights['kantor'] = 10;
            $weights['price'] = 8;
            $weights['cpu'] = 5;
            $weights['gpu'] = -5;
        }
        if (preg_match('/\b(editing|video|desain|grafis|adobe)\b/i', $query)) {
            $weights['editing'] = 10;
            $weights['cpu'] = 8;
            $weights['gpu'] = 6;
        }
        if (preg_match('/\b(gaming|game)\b/i', $query)) {
            $weights['gaming'] = 10;
            $weights['gpu'] = 10;
        }
        if (preg_match('/\b(sekolah|pelajar|belajar|pendidikan)\b/i', $query)) {
            $weights['sekolah'] = 10;
            $weights['price'] = 8;
        }
        if (preg_match('/\b(rtx)\b/i', $query)) {
            $weights['gpu_series'] = 'rtx';
            $weights['gpu'] = 15;
            $weights['gpu_priority'] = 2;
        }
        elseif (preg_match('/\b(gtx)\b/i', $query)) {
            $weights['gpu_series'] = 'gtx';
            $weights['gpu'] = 15;
            $weights['gpu_priority'] = 1;
        }
        if (preg_match('/\b(rtx|gtx|rx)\s*(\d{4})\b/i', $query, $matches)) {
            $weights['gpu_type'] = strtolower($matches[1]);
            $weights['gpu_model'] = $matches[2];
            $weights['gpu'] = 20;
            $weights['gpu_strict'] = true;
        }
        if (preg_match('/\b(intel|core\s*i[3579])\b/i', $query)) {
            $weights['cpu_brand'] = 'intel';
            $weights['cpu'] = 15;
            $weights['cpu_strict'] = true;
        } elseif (preg_match('/\b(amd|ryzen\s*[3579])\b/i', $query)) {
            $weights['cpu_brand'] = 'amd';
            $weights['cpu'] = 15;
            $weights['cpu_strict'] = true;
        }
        if (preg_match('/\bram\s*(\d+)\s*gb\b/i', $query, $matches) || 
            preg_match('/\b(\d+)\s*gb\s*ram\b/i', $query, $matches)) {
            $weights['ram_size'] = (int)$matches[1];
            $weights['ram'] = 15;
            $weights['ram_strict'] = true;
        }
        return $weights;
    }

    private function combineSpecs(Laptop $laptop): string
    {
        return implode(' ', [
            $laptop->brand->name,
            $laptop->series,
            $laptop->model,
            $laptop->description,
            $laptop->cpu,
            $laptop->gpu,
            $laptop->ram . 'GB RAM',
            $laptop->storage,
            $laptop->display
        ]);
    }

    private function tokenize(string $text, int $weight = 1): array
    {
        $text = str_replace(['-', '_', '/'], ' ', strtolower($text));
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        $terms = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $terms = array_diff($terms, $this->stopWords);
        $stemmerFactory = new StemmerFactory();
        $stemmer = $stemmerFactory->createStemmer();
        $terms = array_map(fn($term) => $stemmer->stem($term), $terms);
        $terms = array_map(fn($term) => $this->synonymMapping[$term] ?? $term, $terms);
        preg_match_all('/(\d+)\s?GB/i', $text, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $gb) {
                $terms[] = 'ram_' . $gb . 'gb';
            }
        }
        preg_match_all('/(intel|core)\s*(i[3579])|(amd|ryzen)\s*([3579])/i', $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!empty($match[2])) {
                $terms[] = 'cpu_intel_' . strtolower($match[2]);
            } elseif (!empty($match[4])) {
                $terms[] = 'cpu_amd_r' . $match[4];
            }
        }
        preg_match_all('/(rtx|gtx|rx)\s*(\d{4})/i', $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $terms[] = 'gpu_' . strtolower($match[1]) . '_' . $match[2];
        }
        return array_merge(...array_fill(0, $weight, $terms));
    }

    private function calculateScores($laptopDocs, $queryTerms, $idf, $contextWeights): array
    {
        $scores = [];
        foreach ($laptopDocs as $id => $terms) {
            $tf = $this->calculateTF($terms);
            $score = 0;
            foreach ($queryTerms as $term) {
                if (isset($tf[$term], $idf[$term])) {
                    $termWeight = $contextWeights[$term] ?? 1;
                    $score += ($tf[$term] * $idf[$term]) * $termWeight;
                }
            }
            $laptop = Laptop::find($id);
            $score += $this->calculateSpecScore($laptop, $contextWeights);
            $scores[$id] = $score;
        }
        arsort($scores);
        return $scores;
    }

    private function calculateSpecScore(Laptop $laptop, array $weights): float
    {
        $score = 0;
        if ($weights['gpu'] >= 3 && !$this->isDedicatedGPU($laptop->gpu)) {
            $score -= 3;
        }
        if ($weights['cpu'] >= 3 && !$this->isHighEndCPU($laptop->cpu)) {
            $score -= 4;
        }
        if (isset($weights['price_range'])) {
            [$min, $max] = $weights['price_range'];
            $mid = ($min + $max) / 2;
            $score += $this->calculatePriceScore($laptop->price, $mid);
        } else if ($weights['price'] >= 3) {
            $score += $this->calculatePriceScore($laptop->price, 8000000);
        }
        if ($weights['kantor'] > 0) {
            if ($this->isGamingLaptop($laptop)) $score -= 10;
            if ($this->isDedicatedGPU($laptop->gpu)) $score -= 8;
            if ($this->isHighEndCPU($laptop->cpu)) $score -= 5;
            if ($laptop->price > 15000000) $score -= 5;
            if (!$this->isDedicatedGPU($laptop->gpu)) $score += 5;
            if ($laptop->price < 10000000) $score += 3;
            if (preg_match('/i3|i5|ryzen 3|ryzen 5/i', $laptop->cpu)) $score += 2;
        }
        if ($weights['sekolah'] > 0) {
            if ($this->isGamingLaptop($laptop)) $score -= 10;
            if ($laptop->price > 10000000) $score -= 8;
            if (preg_match('/i7|i9|ryzen 7|ryzen 9/i', $laptop->cpu)) $score -= 5;
            if ($laptop->price < 8000000) $score += 5;
            if (preg_match('/i3|ryzen 3|pentium|celeron/i', $laptop->cpu)) $score += 3;
        }
        if ($weights['gaming'] > 0) {
            if (!$this->isDedicatedGPU($laptop->gpu)) $score -= 10;
            if ($laptop->ram < 8) $score -= 8;
            if ($this->isDedicatedGPU($laptop->gpu)) $score += 5;
            if ($laptop->ram >= 16) $score += 3;
        }
        if ($weights['editing'] > 0) {
            if (!$this->isHighEndCPU($laptop->cpu)) $score -= 8;
            if ($laptop->ram < 16) $score -= 5;
            if ($this->isHighEndCPU($laptop->cpu)) $score += 5;
            if ($laptop->ram >= 32) $score += 3;
        }
        if (isset($weights['cpu_strict'])) {
            $laptopCpu = strtolower($laptop->cpu);
            $brand = $weights['cpu_brand'];
            if ($brand === 'amd') {
                if (strpos($laptopCpu, 'amd') !== false ||
                    strpos($laptopCpu, 'ryzen') !== false) {
                    $score += 15;
                } else {
                    $score -= 15;
                }
            }
            elseif ($brand === 'intel') {
                if (strpos($laptopCpu, 'intel') !== false ||
                    strpos($laptopCpu, 'core') !== false) {
                    $score += 15;
                } else {
                    $score -= 15;
                }
            }
        }
        if (isset($weights['ram_strict'])) {
            $ramSize = $weights['ram_size'];
            if ($laptop->ram == $ramSize) {
                $score += 10;
            } elseif ($laptop->ram > $ramSize && $laptop->ram <= $ramSize * 1.5) {
                $score += 5;
            } elseif ($laptop->ram > $ramSize * 1.5) {
                $score -= 3;
            } else {
                $score -= 10;
            }
        }
        if (isset($weights['gpu_strict'])) {
            $laptopGpu = strtolower($laptop->gpu);
            $gpuType = $weights['gpu_type'];
            if (strpos($laptopGpu, $gpuType) !== false) {
                $score += 10;
                if (isset($weights['gpu_model'])) {
                    $model = $weights['gpu_model'];
                    if (preg_match("/{$gpuType}.*{$model}/", $laptopGpu)) {
                        $score += 5;
                    } else {
                        $score -= 5;
                    }
                }
            } else {
                $score -= 10;
            }
        }
        if (isset($weights['gpu_series'])) {
            $laptopGpu = strtolower($laptop->gpu);
            $series = $weights['gpu_series'];
            if (str_contains($laptopGpu, $series)) {
                $score += $weights['gpu_priority'] * 10;
                $modelScore = $this->extractGPUModelScore($laptop->gpu, $series);
                $score += $modelScore * 0.5;
            } else {
                $score -= 15;
            }
        }
        return $score;
    }

    private function extractGPUModelScore(string $gpu, string $series): int
    {
        $gpu = strtolower($gpu);
        $pattern = '/' . $series . '\s*(\d{4})/';
        if (preg_match($pattern, $gpu, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }

    private function isDedicatedGPU(string $gpu): bool
    {
        return preg_match('/RTX \d{4}|GTX \d{4}|Radeon RX/i', $gpu);
    }

    private function isHighEndCPU(string $cpu): bool
    {
        return preg_match('/i[579]|Ryzen [79]/i', $cpu);
    }

    private function calculatePriceScore(float $price, float $target): float
    {
        $diff = abs($price - $target);
        $maxDiff = 2000000;
        $normalizedDiff = min($diff, $maxDiff) / $maxDiff;
        return 5 * (1 - $normalizedDiff);
    }

    private function calculateTF(array $terms): array
    {
        $tf = [];
        $counts = array_count_values($terms);
        foreach ($counts as $term => $count) {
            $tf[$term] = $count > 0 ? (1 + log($count)) : 0;
        }
        return $tf;
    }

    private function calculateIDF(Collection $documents): array
    {
        $idf = [];
        $totalDocs = $documents->count();
        $docFreq = [];
        foreach ($documents as $doc) {
            foreach (array_unique($doc) as $term) {
                $docFreq[$term] = ($docFreq[$term] ?? 0) + 1;
            }
        }
        foreach ($docFreq as $term => $df) {
            $idf[$term] = log(($totalDocs + 1) / ($df + 0.5)) + 1;
        }
        return $idf;
    }

    private function getSortedResults(array $scores, array $contextWeights): Collection
    {
        return collect($scores)
            ->filter(function ($score, $id) use ($contextWeights) {
                $laptop = Laptop::find($id);
                if ($laptop->ram < 4) return false;
                if ($contextWeights['kantor'] > 0) {
                    if ($this->isGamingLaptop($laptop)) return false;
                    if ($this->isDedicatedGPU($laptop->gpu)) return false;
                    if ($laptop->price > 15000000) return false;
                }
                if ($contextWeights['sekolah'] > 0) {
                    if ($this->isGamingLaptop($laptop)) return false;
                    if ($laptop->price > 10000000) return false;
                    if (!preg_match('/i3|ryzen 3|pentium|celeron/i', strtolower($laptop->cpu))) return false;
                }
                if ($contextWeights['gaming'] > 0) {
                    if (!$this->isDedicatedGPU($laptop->gpu)) return false;
                    if ($laptop->ram < 8) return false;
                }
                if ($contextWeights['editing'] > 0) {
                    if (!$this->isHighEndCPU($laptop->cpu)) return false;
                    if ($laptop->ram < 16) return false;
                }
                if (isset($contextWeights['cpu_strict'])) {
                    $laptopCpu = strtolower($laptop->cpu);
                    $brand = $contextWeights['cpu_brand'];
                    if ($brand === 'amd') {
                        if (!preg_match('/(amd|ryzen)/', $laptopCpu)) {
                            return false;
                        }
                    }
                    elseif ($brand === 'intel') {
                        if (!preg_match('/(intel|core)/', $laptopCpu)) {
                            return false;
                        }
                    }
                }
                if (isset($contextWeights['ram_strict'])) {
                    $ramSize = $contextWeights['ram_size'];
                    if ($laptop->ram < $ramSize) {
                        return false;
                    }
                    if ($laptop->ram > $ramSize * 1.5) {
                        return false;
                    }
                }
                if (isset($contextWeights['gpu_strict'])) {
                    $laptopGpu = strtolower($laptop->gpu);
                    $gpuType = $contextWeights['gpu_type'];
                    if (!str_contains($laptopGpu, $gpuType)) {
                        return false;
                    }
                    if (isset($contextWeights['gpu_model'])) {
                        $model = $contextWeights['gpu_model'];
                        if (!preg_match("/{$gpuType}.*{$model}/", $laptopGpu)) {
                            return false;
                        }
                    }
                }
                if (isset($contextWeights['gpu_series'])) {
                    $laptopGpu = strtolower($laptop->gpu);
                    $series = $contextWeights['gpu_series'];
                    if (!str_contains($laptopGpu, $series)) {
                        return false;
                    }
                }
                return $score > 0;
            })
            ->sortByDesc(function ($score, $id) use ($contextWeights) {
                $laptop = Laptop::find($id);
                $finalScore = $score;
                if ($contextWeights['kantor'] > 0) {
                    if (!$this->isDedicatedGPU($laptop->gpu)) $finalScore += 5;
                    if ($laptop->price < 10000000) $finalScore += 3;
                    if (preg_match('/i3|i5|ryzen 3|ryzen 5/i', $laptop->cpu)) $finalScore += 2;
                }
                if ($contextWeights['sekolah'] > 0) {
                    if ($laptop->price < 8000000) $finalScore += 5;
                    if (preg_match('/i3|ryzen 3|pentium|celeron/i', $laptop->cpu)) $finalScore += 3;
                }
                if ($contextWeights['gaming'] > 0) {
                    if ($this->isDedicatedGPU($laptop->gpu)) $finalScore += 5;
                    if ($laptop->ram >= 16) $finalScore += 3;
                }
                if ($contextWeights['editing'] > 0) {
                    if ($this->isHighEndCPU($laptop->cpu)) $finalScore += 5;
                    if ($laptop->ram >= 32) $finalScore += 3;
                }
                if (isset($contextWeights['cpu_strict'])) {
                    $laptopCpu = strtolower($laptop->cpu);
                    $brand = $contextWeights['cpu_brand'];
                    if ($brand === 'amd' && str_contains($laptopCpu, 'ryzen')) {
                        $finalScore += 5;
                    }
                    elseif ($brand === 'intel' && preg_match('/core\s*i[3579]/', $laptopCpu)) {
                        $finalScore += 5;
                    }
                }
                if (isset($contextWeights['ram_strict'])) {
                    $ramSize = $contextWeights['ram_size'];
                    if ($laptop->ram == $ramSize) {
                        $finalScore += 5;
                    }
                }
                if (isset($contextWeights['gpu_series']) && !isset($contextWeights['gpu_model'])) {
                    $series = $contextWeights['gpu_series'];
                    $modelScore = $this->extractGPUModelScore($laptop->gpu, $series);
                    $finalScore += $modelScore * 0.1;
                    if ($series === 'rtx') {
                        $finalScore += 5;
                    }
                }
                return $finalScore;
            })
            ->take(20)
            ->keys()
            ->pipe(function ($ids) {
                return Laptop::whereIn('id', $ids)
                    ->with('brand')
                    ->get();
            });
    }

    private function isGamingLaptop(Laptop $laptop): bool
    {
        $text = strtolower($laptop->brand->name . ' ' . $laptop->series . ' ' . $laptop->model);
        return preg_match('/rtx|gtx|rog|alienware|predator|legion|gaming|msi|razer|geforce|rxt|tuf|nitro|omen|ge66|ge76|gs66|gl63|gp66|strix|scar|aorus|eluktronics|ideapad gaming|nitro|helios|trident|mech|vector|bravo|delta|alpha|beta/i', $text);
    }

    private function isNegativeSentence(string $text): bool
    {
        $indicators = [
            'hanya bisa untuk',
            'kurang cocok untuk',
            'tidak cocok untuk',
            'tidak kuat untuk',
            'tidak bagus untuk',
            'kurang bagus buat',
            'tidak direkomendasikan untuk',
            'lemot saat',
            'kurang nyaman untuk',
            'kurang optimal buat',
            'tidak support',
            'sering lag',
            'cepat panas saat',
            'frame drop saat',
        ];
        foreach ($indicators as $phrase) {
            if (str_contains($text, $phrase)) return true;
        }
        return false;
    }

    private function isPositiveSentence(string $text): bool
    {
        $indicators = [
            'cocok untuk',
            'bagus untuk',
            'sangat baik untuk',
            'lancar digunakan untuk',
            'mendukung untuk',
            'powerful untuk',
            'ideal untuk',
            'terbaik untuk',
            'mantap buat',
            'oke buat',
            'recommended buat',
            'responsif saat',
            'mulus saat digunakan',
        ];
        foreach ($indicators as $phrase) {
            if (str_contains($text, $phrase)) return true;
        }
        return false;
    }

    private function reviewMismatchWithSpecs(string $reviewText, Laptop $laptop): bool
    {
        $reviewText = strtolower($reviewText);
        if (str_contains($reviewText, 'bagus untuk editing') && (
            !$this->isHighEndCPU($laptop->cpu) || $laptop->ram < 16
        )) {
            return true;
        }
        if (str_contains($reviewText, 'lancar untuk gaming') && (
            !$this->isDedicatedGPU($laptop->gpu) || $laptop->ram < 8
        )) {
            return true;
        }
        if (str_contains($reviewText, 'bagus untuk kerja kantor') && (
            $this->isGamingLaptop($laptop) || $laptop->ram < 8
        )) {
            return true;
        }
        if (str_contains($reviewText, 'bagus untuk sekolah') && (
            $laptop->ram < 4 || $laptop->price > 10000000
        )) {
            return true;
        }
        return false;
    }

    private function getStopWords(): array
    {
        return [
            'untuk', 'ke', 'dari', 'yang', 'dan', 'atau', 'adalah', 'ini', 'itu', 'dengan', 'pada',
            'saya', 'kamu', 'dia', 'kami', 'kita', 'mereka', 'di', 'dalam', 'tanpa', 'atas', 'bawah',
            'depan', 'belakang', 'samping', 'tentang', 'sebagai', 'seperti', 'karena', 'sehingga',
            'tetapi', 'namun', 'jika', 'maka', 'ketika', 'sambil', 'meskipun', 'sementara', 'apakah',
            'bagaimana', 'dimana', 'kapan', 'siapa', 'mengapa'
        ];
    }

    private function getSynonymMapping(): array
    {
        return [
            'sekolah' => 'entrylevel', 'pelajar' => 'entrylevel', 'pendidikan' => 'basic', 'mahasiswa' => 'entrylevel',
            'editing' => 'highperformance', 'desain' => 'creative', 'video' => 'render', 'grafis' => 'creative', 'animasi' => 'creative',
            'gaming' => 'gpu_dedicated', 'game' => 'gpu_dedicated', 'rtx' => 'gpu_dedicated', 'gtx' => 'gpu_dedicated', 'rx' => 'gpu_dedicated',
            'kantor' => 'office', 'bisnis' => 'office', 'pekerjaan' => 'office', 'kerja' => 'office',
            'processor' => 'cpu', 'prosesor' => 'cpu', 'cpu' => 'processor', 'graphics' => 'gpu', 'grafis' => 'gpu', 'gpu' => 'graphics',
            'ssd' => 'storage', 'harddisk' => 'storage', 'hdd' => 'storage', 'memory' => 'ram', 'ram' => 'memory', 'disk' => 'storage',
            'layar' => 'display', 'screen' => 'display', 'baterai' => 'battery',
            'nyaman' => 'ergonomis ringan', 'panas' => 'overheating temperatur tinggi', 'awet' => 'tahan lama durability',
            'lemot' => 'lambat performa rendah', 'cepat' => 'responsif kencang', 'lambat' => 'lelet lemot',
            'tipis' => 'ringan portable', 'berat' => 'tebal besar'
        ];
    }
}