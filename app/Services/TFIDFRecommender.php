<?php

namespace App\Services;

use App\Models\Laptop;
use Illuminate\Support\Collection;
use Sastrawi\Stemmer\StemmerFactory;

class TFIDFRecommender
{
    private $stopWords;
    private $synonymMapping;

    public function __construct()
    {
        $this->stopWords = $this->getStopWords();
        $this->synonymMapping = $this->getSynonymMapping();
    }

    public function recommend(string $query): Collection
    {
        $contextWeights = $this->detectQueryContext($query);
        $laptops = Laptop::with('reviews', 'brand')->get();
        $allDocsForIDF = collect();
        $laptopDocs = collect();

        foreach ($laptops as $laptop) {
            $specsText = $this->combineSpecs($laptop);
            $specsTerms = $this->tokenize($specsText, 3); // Bobot 3x
            $allDocsForIDF->push($specsTerms);

            $reviewTerms = [];
            foreach ($laptop->reviews as $review) {
                $reviewText = strtolower($review->review);

                // Filter berdasarkan konteks positif/negatif
                if ($this->isNegativeSentence($reviewText)) continue;
                if (! $this->isPositiveSentence($reviewText)) continue;

                $revTerms = $this->tokenize($review->review);
                $allDocsForIDF->push($revTerms);
                $reviewTerms = array_merge($reviewTerms, $revTerms);
            }

            $laptopDocs[$laptop->id] = array_merge($specsTerms, $reviewTerms);
        }

        $idf = $this->calculateIDF($allDocsForIDF);

        $queryTerms = $this->tokenize($query);
        $scores = $this->calculateScores($laptopDocs, $queryTerms, $idf, $contextWeights);

        return $this->getSortedResults($scores, $contextWeights);
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

        // Tambah keyword yang lebih spesifik
        $query = strtolower($query);
        if (preg_match('/sekolah|pelajar|belajar|pendidikan/i', $query)) {
            $weights['price'] = 4;
            $weights['cpu'] = 2;
            $weights['battery'] = 3; // Tambah parameter baru
        } elseif (preg_match('/editing|video|desain grafis|adobe/i', $query)) {
            $weights['cpu'] = 4;
            $weights['gpu'] = 3;
            $weights['ram'] = 3;
            $weights['storage'] = 2;
            $weights['display'] = 2; // Tambah parameter warna
        } elseif (preg_match('/gaming|game|rtx|gtx/i', $query)) {
            $weights['gpu'] = 5;
            $weights['cooling'] = 3;
            $weights['cpu'] = 3;
            $weights['refresh'] = 2; // Tambah parameter refresh rate
        } elseif (preg_match('/kantor|office|pekerjaan|bisnis/i', $query)) {
            $weights['office'] = 5;
            $weights['cpu'] = 2;
            $weights['ram'] = 2;
            $weights['price'] = 4;
            $weights['gpu'] = -2; // Penalti GPU gaming
        }
        return $weights;
    }

    private function preprocessSpecs(Laptop $laptop): string
    {
        return implode(' ', [
            $this->brandMapping[strtolower($laptop->brand)] ?? $laptop->brand,
            preg_replace('/\s+/', ' ', $laptop->model),
            $this->classifyCPU($laptop->cpu),
            $this->classifyGPU($laptop->gpu),
            "ram".$this->extractNumber($laptop->ram, 'GB')."gb",
            "storage".$this->extractNumber($laptop->storage, ['GB','TB']),
            $laptop->price < 8000000 ? 'murah' : 'mahal'
        ]);
    }

    private function classifyCPU(string $cpu): string
    {
        $cpu = strtolower($cpu);
        if (preg_match('/(i3|ryzen 3|celeron|pentium)/', $cpu)) return 'cpu_low';
        if (preg_match('/(i5|ryzen 5)/', $cpu)) return 'cpu_mid';
        if (preg_match('/(i7|i9|ryzen 7|ryzen 9)/', $cpu)) return 'cpu_high';
        return 'cpu_unknown';
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
            $laptop->ram,
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
        $text .= ' '.implode(' ', $matches[1]).'gb';

        preg_match_all('/(i\d+)\s(\d+)(th|nd|rd)/i', $text, $matches);
        foreach ($matches[1] as $key => $cpu) {
            $text .= ' gen'.$matches[2][$key];
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
                    // Terapkan bobot konteks
                    $termWeight = $contextWeights[$term] ?? 1;
                    $score += ($tf[$term] * $idf[$term]) * $termWeight;
                }
            }

            // Tambahkan penilaian spesifikasi khusus
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
        
        // Critical spec checks
        if ($weights['gpu'] >= 3 && !$this->isDedicatedGPU($laptop->gpu)) {
            return -10; // Penalty untuk GPU tidak memenuhi
        }
        
        if ($weights['cpu'] >= 3 && !$this->isHighEndCPU($laptop->cpu)) {
            return -5; // Penalty untuk CPU tidak memenuhi
        }

        // Dynamic scoring
        if ($weights['price'] >= 3) {
            $score += $this->calculatePriceScore($laptop->price, 8000000);
        }
        
        if ($weights['gpu'] >= 3) {
            $score += $this->calculateGPUScore($laptop->gpu);
        }

        if ($weights['kantor'] > 0) {
            if ($this->isGamingLaptop($laptop)) return -20;
            if ($laptop->ram < 8) return -10;
        }

        if ($weights['gaming'] > 0) {
            if (!$this->isDedicatedGPU($laptop->gpu)) return -20;
            if ($laptop->ram < 8) return -10;
        }

        if ($weights['editing'] > 0) {
            if (!$this->isHighEndCPU($laptop->cpu)) return -20;
            if ($laptop->ram < 16) return -10;
        }

        if ($weights['sekolah'] > 0) {
            if ($laptop->ram < 4) return -10;
            if ($laptop->price > 10000000) return -5;
        }

        return $score;
    }

    private function isDedicatedGPU(string $gpu): bool
    {
        return preg_match('/RTX \d{4}|GTX \d{4}|Radeon RX/i', $gpu);
    }

    private function isHighEndCPU(string $cpu): bool
    {
        return preg_match('/i[579]|Ryzen [79]/i', $cpu);
    }

    private function calculateGPUScore(string $gpu): float
    {
        if (preg_match('/RTX (\d{4})/', $gpu, $matches)) {
            $model = (int)$matches[1];
            return $model >= 3060 ? 5 : 3;
        }
        return 0;
    }

    private function calculatePriceScore(float $price, float $target): float
    {
        // Semakin dekat ke target, semakin tinggi skornya (maksimum 5)
        if ($price <= $target) {
            return 5;
        }
        // Skor menurun secara linier, minimum 0
        $score = max(0, 5 - (($price - $target) / $target) * 5);
        return $score;
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
                
                // Filter tanpa weight
                if ($contextWeights['kantor'] > 0) {
                    return !$this->isGamingLaptop($laptop) &&
                           $this->classifyGPU($laptop->gpu) === 'gpu_integrated';
                }
                
                return $score > 0;
            })
            ->sortDesc()
            ->take(10)
            ->keys()
            ->pipe(function ($ids) {
                return Laptop::whereIn('id', $ids)->get();
            });
    }

    private function getPriceThreshold(): float
    {
        if (str_contains(request()->query('q'), 'sekolah')) {
            return 10000000;
        }
        return 30000000;
    }

    private function meetsMinimumSpecs(Laptop $laptop): bool
    {
        $query = strtolower(request()->query('q'));
        if (str_contains($query, 'gaming')) {
            return $this->isDedicatedGPU($laptop->gpu) &&
                $laptop->ram >= 8;
        }
        if (str_contains($query, 'sekolah')) {
            return $laptop->ram >= 4 &&
                preg_match('/i3|Ryzen [3]/i', $laptop->cpu);
        }
        if (str_contains($query, 'editing')) {
            return $laptop->ram >= 16 &&
                preg_match('/i[7-9]|Ryzen [7-9]/i', $laptop->cpu);
        }
        if (str_contains($query, 'kerja')) {
            return !$this->isGamingLaptop($laptop) &&
                    $laptop->ram >= 8;
        }
        return true;
    }

    private function isGamingLaptop(Laptop $laptop): bool
    {
        return preg_match('/rtx|gtx|rog|alienware|predator/i',
                $laptop->brand.' '.$laptop->model);
    }

    private function classifyGPU(string $gpu): string
    {
        $gpu = strtolower($gpu);
        if (preg_match('/(intel uhd|iris xe|radeon graphics)/', $gpu)) return 'gpu_integrated';
        if (preg_match('/(rtx|gtx|radeon rx)/', $gpu)) return 'gpu_dedicated';
        return 'gpu_unknown';
    }

    // === Filter tambahan ===
    private function isNegativeSentence(string $text): bool
    {
        foreach ($this->getNegativeIndicators() as $phrase) {
            if (str_contains($text, $phrase)) return true;
        }
        return false;
    }

    private function isPositiveSentence(string $text): bool
    {
        foreach ($this->getPositiveIndicators() as $phrase) {
            if (str_contains($text, $phrase)) return true;
        }
        return false;
    }

    private function getNegativeIndicators(): array
    {
        return [
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
    }

    private function getPositiveIndicators(): array
    {
        return [
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
    }

    private function getStopWords(): array
    {
        return [
            'untuk',
            'ke',
            'dari',
            'yang',
            'dan',
            'atau',
            'adalah',
            'ini',
            'itu',
        ];
    }

    private function getSynonymMapping(): array
    {
        return [
            // Untuk sekolah
            'sekolah' => 'entrylevel',
            'pelajar' => 'entrylevel',
            'pendidikan' => 'basic',

            // Untuk editing
            'editing' => 'highperformance',
            'desain' => 'creative',
            'video' => 'render',

            // Teknis
            'processor' => 'cpu',
            'cpu' => 'processor',
            'graphics' => 'gpu',
            'gpu' => 'graphics',
            'ssd' => 'storage',
            'hdd' => 'storage',
            'memory' => 'ram',
            'ram' => 'memory',

            //Fitur umum
            'nyaman' => 'ergonomis ringan',
            'panas' => 'overheating temperatur tinggi',
            'awet' => 'tahan lama durability',
            'lemot' => 'lambat performa rendah',

        ];
    }

    private function extractNumber(string $text, $units = [])
    {
        if (!is_array($units)) {
            $units = [$units];
        }
        $pattern = '/(\d+(?:\.\d+)?)(?:\s*)(' . implode('|', array_map('preg_quote', $units)) . ')?/i';
        if (preg_match($pattern, $text, $matches)) {
            return is_numeric($matches[1]) ? (float)$matches[1] : null;
        }
        return null;
    }
}
