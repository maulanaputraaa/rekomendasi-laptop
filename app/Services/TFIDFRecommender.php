<?php

namespace App\Services;

use App\Models\Laptop;
use Illuminate\Support\Collection;
use Sastrawi\Stemmer\StemmerFactory;
use Illuminate\Support\Facades\Log;

/**
 * Layanan Rekomendasi Berbasis TF-IDF
 * 
 * Sistem ini merekomendasikan laptop berdasarkan:
 * 1. Relevansi teks query dengan spesifikasi laptop
 * 2. Konteks penggunaan (gaming, kantor, editing, sekolah)
 * 3. Review positif dari pengguna
 * 4. Penanganan sinonim dan normalisasi bahasa
 */
class TFIDFRecommender
{
    /** @var array Daftar kata stopword bahasa Indonesia */
    private $stopWords;
    
    /** @var array Pemetaan sinonim untuk normalisasi istilah teknis */
    private $synonymMapping;

    public function __construct()
    {
        $this->stopWords = $this->getStopWords();
        $this->synonymMapping = $this->getSynonymMapping();
    }

    /**
     * Memberikan rekomendasi laptop berdasarkan query
     * 
     * Alur kerja:
     * 1. Deteksi konteks query (gaming, kantor, dll)
     * 2. Ambil semua laptop dan siapkan dokumen teks
     * 3. Hitung TF-IDF untuk setiap laptop
     * 4. Berikan skor tambahan berdasarkan konteks
     * 5. Urutkan dan filter hasil
     * 
     * @param string $query Query pencarian pengguna
     * @param array|null $priceRange Rentang harga [min, max] (opsional)
     * @return Collection Rekomendasi laptop beserta skor TF-IDF
     */
    public function recommend(string $query, ?array $priceRange = null): Collection
    {
        // Deteksi konteks query dan tentukan bobot fitur
        $contextWeights = $this->detectQueryContext($query);
        
        // Terapkan filter harga jika ada
        if ($priceRange) {
            $contextWeights['price_range'] = $priceRange;
            $contextWeights['price'] = 5; // Tingkatkan prioritas harga
        }
        
        // Ambil semua laptop dengan relasi
        $laptops = Laptop::with('reviews', 'brand')->get();
        
        // Siapkan dokumen untuk perhitungan IDF
        [$allDocsForIDF, $laptopDocs] = $this->prepareDocuments($laptops);
        
        // Hitung IDF dari seluruh dokumen
        $idf = $this->calculateIDF($allDocsForIDF);
        
        // Tokenisasi query
        $queryTerms = $this->tokenize($query);
        
        // Hitung skor untuk setiap laptop
        $scores = $this->calculateScores($laptopDocs, $queryTerms, $idf, $contextWeights);
        
        // Urutkan dan kembalikan hasil
        return $this->getSortedResults($scores, $contextWeights)
            ->map(function ($laptop) use ($scores) {
                $laptop->tfidf_score = $scores[$laptop->id] ?? 0;
                return $laptop;
            });
    }

    /**
     * Mendeteksi konteks query dan menentukan bobot fitur
     * 
     * Konteks yang didukung:
     * - Kantor: Tingkatkan bobot harga dan CPU, kurangi GPU
     * - Gaming: Tingkatkan bobot GPU secara signifikan
     * - Editing: Tingkatkan bobot CPU dan GPU
     * - Sekolah: Tingkatkan bobot harga
     * 
     * Juga mendeteksi spesifikasi eksplisit:
     * - GPU (RTX 4060, GTX 1650)
     * - CPU (Intel i7, Ryzen 5)
     * - RAM (8GB, 16GB)
     * 
     * @param string $query Query pencarian
     * @return array Bobot fitur yang disesuaikan
     */
    private function detectQueryContext(string $query): array
    {
        // Bobot default untuk setiap fitur
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
        
        // Deteksi konteks penggunaan
        if (preg_match('/\b(kantor|office|pekerjaan|bisnis)\b/i', $query)) {
            $weights['kantor'] = 10;
            $weights['price'] = 8;
            $weights['cpu'] = 5;
            $weights['gpu'] = -5; // GPU tidak penting untuk kantor
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
        
        // Deteksi GPU spesifik
        if (preg_match('/\b(rtx)\b/i', $query)) {
            $weights['gpu_series'] = 'rtx';
            $weights['gpu'] = 15;
            $weights['gpu_priority'] = 2; // Prioritas tinggi
        }
        elseif (preg_match('/\b(gtx)\b/i', $query)) {
            $weights['gpu_series'] = 'gtx';
            $weights['gpu'] = 15;
            $weights['gpu_priority'] = 1; // Prioritas sedang
        }
        
        // Deteksi model GPU spesifik (RTX 4060, GTX 1650)
        if (preg_match('/\b(rtx|gtx|rx)\s*(\d{4})\b/i', $query, $matches)) {
            $weights['gpu_type'] = strtolower($matches[1]);
            $weights['gpu_model'] = $matches[2];
            $weights['gpu'] = 20;
            $weights['gpu_strict'] = true; // Mode ketat
        }
        
        // Deteksi brand CPU (Intel/AMD)
        if (preg_match('/\b(intel|core\s*i[3579])\b/i', $query)) {
            $weights['cpu_brand'] = 'intel';
            $weights['cpu'] = 15;
            $weights['cpu_strict'] = true;
        } elseif (preg_match('/\b(amd|ryzen\s*[3579])\b/i', $query)) {
            $weights['cpu_brand'] = 'amd';
            $weights['cpu'] = 15;
            $weights['cpu_strict'] = true;
        }
        
        // Deteksi ukuran RAM spesifik
        if (preg_match('/\bram\s*(\d+)\s*gb\b/i', $query, $matches) || 
            preg_match('/\b(\d+)\s*gb\s*ram\b/i', $query, $matches)) {
            $weights['ram_size'] = (int)$matches[1];
            $weights['ram'] = 15;
            $weights['ram_strict'] = true;
        }
        
        return $weights;
    }

    /**
     * Mempersiapkan dokumen untuk perhitungan TF-IDF
     * 
     * @param Collection $laptops Koleksi laptop
     * @return array Tuple [$allDocsForIDF, $laptopDocs]
     */
    private function prepareDocuments(Collection $laptops): array
    {
        $allDocsForIDF = collect();
        $laptopDocs = collect();
        
        foreach ($laptops as $laptop) {
            // Gabungkan spesifikasi laptop menjadi satu teks
            $specsText = $this->combineSpecs($laptop);
            $specsTerms = $this->tokenize($specsText, 3); // Beri bobot lebih
            
            $allDocsForIDF->push($specsTerms);
            $reviewTerms = [];
            
            // Proses review
            foreach ($laptop->reviews as $review) {
                $reviewText = strtolower($review->review);
                
                // Lewati review negatif atau tidak relevan
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
            
            // Gabungkan term spesifikasi dan review
            $laptopDocs[$laptop->id] = array_merge($specsTerms, $reviewTerms);
        }
        
        return [$allDocsForIDF, $laptopDocs];
    }

    /**
     * Menggabungkan spesifikasi laptop menjadi string tunggal
     * 
     * @param Laptop $laptop Model laptop
     * @return string Teks gabungan spesifikasi
     */
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

    /**
     * Tokenisasi teks dengan proses:
     * 1. Case folding (ke lowercase)
     * 2. Penghapusan karakter khusus
     * 3. Filter stopword
     * 4. Stemming
     * 5. Pemetaan sinonim
     * 6. Ekstraksi fitur khusus (RAM, CPU, GPU)
     * 
     * @param string $text Teks input
     * @param int $weight Bobot pengulangan term
     * @return array Term yang sudah dinormalisasi
     */
    private function tokenize(string $text, int $weight = 1): array
    {
        // Normalisasi teks
        $text = str_replace(['-', '_', '/'], ' ', strtolower($text));
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        
        // Pisahkan term
        $terms = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Filter stopword
        $terms = array_diff($terms, $this->stopWords);
        
        // Stemming bahasa Indonesia
        $stemmerFactory = new StemmerFactory();
        $stemmer = $stemmerFactory->createStemmer();
        $terms = array_map(fn($term) => $stemmer->stem($term), $terms);
        
        // Pemetaan sinonim
        $terms = array_map(fn($term) => $this->synonymMapping[$term] ?? $term, $terms);
        
        // Ekstrak fitur khusus
        $terms = $this->extractSpecialFeatures($terms, $text);
        
        // Terapkan bobot
        return array_merge(...array_fill(0, $weight, $terms));
    }

    /**
     * Ekstrak fitur khusus dari teks:
     * - Ukuran RAM (8GB → 'ram_8gb')
     * - Model CPU (i5 → 'cpu_intel_i5')
     * - Model GPU (RTX 3060 → 'gpu_rtx_3060')
     * 
     * @param array $terms Daftar term
     * @param string $text Teks asli
     * @return array Term yang diperkaya
     */
    private function extractSpecialFeatures(array $terms, string $text): array
    {
        // Ekstrak ukuran RAM
        preg_match_all('/(\d+)\s?GB/i', $text, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $gb) {
                $terms[] = 'ram_' . $gb . 'gb';
            }
        }
        
        // Ekstrak model CPU
        preg_match_all('/(intel|core)\s*(i[3579])|(amd|ryzen)\s*([3579])/i', $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!empty($match[2])) {
                $terms[] = 'cpu_intel_' . strtolower($match[2]);
            } elseif (!empty($match[4])) {
                $terms[] = 'cpu_amd_r' . $match[4];
            }
        }
        
        // Ekstrak model GPU
        preg_match_all('/(rtx|gtx|rx)\s*(\d{4})/i', $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $terms[] = 'gpu_' . strtolower($match[1]) . '_' . $match[2];
        }
        
        return $terms;
    }

    /**
     * Menghitung skor TF-IDF dengan penyesuaian konteks
     * 
     * @param Collection $laptopDocs Dokumen per laptop
     * @param array $queryTerms Term query
     * @param array $idf Nilai IDF
     * @param array $contextWeights Bobot konteks
     * @return array Skor per laptop [id => score]
     */
    private function calculateScores($laptopDocs, $queryTerms, $idf, $contextWeights): array
    {
        $scores = [];
        
        foreach ($laptopDocs as $id => $terms) {
            // Hitung TF
            $tf = $this->calculateTF($terms);
            
            // Hitung skor dasar TF-IDF
            $score = 0;
            foreach ($queryTerms as $term) {
                if (isset($tf[$term], $idf[$term])) {
                    $termWeight = $contextWeights[$term] ?? 1;
                    $score += ($tf[$term] * $idf[$term]) * $termWeight;
                }
            }
            
            // Tambahkan skor spesifikasi berbasis konteks
            $laptop = Laptop::find($id);
            $score += $this->calculateSpecScore($laptop, $contextWeights);
            
            $scores[$id] = $score;
        }
        
        arsort($scores);
        return $scores;
    }

    /**
     * Menghitung skor tambahan berdasarkan kesesuaian spesifikasi dengan konteks
     * 
     * @param Laptop $laptop Model laptop
     * @param array $weights Bobot konteks
     * @return float Skor tambahan
     */
    private function calculateSpecScore(Laptop $laptop, array $weights): float
    {
        $score = 0;
        
        // Penalty untuk konteks tertentu
        if ($weights['gpu'] >= 3 && !$this->isDedicatedGPU($laptop->gpu)) {
            $score -= 3; // Penalty GPU tidak dedicated
        }
        
        if ($weights['cpu'] >= 3 && !$this->isHighEndCPU($laptop->cpu)) {
            $score -= 4; // Penalty CPU tidak high-end
        }
        
        // Penyesuaian harga
        if (isset($weights['price_range'])) {
            [$min, $max] = $weights['price_range'];
            $mid = ($min + $max) / 2;
            $score += $this->calculatePriceScore($laptop->price, $mid);
        } else if ($weights['price'] >= 3) {
            $score += $this->calculatePriceScore($laptop->price, 8000000);
        }
        
        // Optimasi untuk konteks kantor
        if ($weights['kantor'] > 0) {
            if ($this->isGamingLaptop($laptop)) $score -= 10;
            if ($this->isDedicatedGPU($laptop->gpu)) $score -= 8;
            if ($this->isHighEndCPU($laptop->cpu)) $score -= 5;
            if ($laptop->price > 15000000) $score -= 5;
            if (!$this->isDedicatedGPU($laptop->gpu)) $score += 5;
            if ($laptop->price < 10000000) $score += 3;
            if (preg_match('/i3|i5|ryzen 3|ryzen 5/i', $laptop->cpu)) $score += 2;
        }
        
        // Optimasi untuk konteks sekolah
        if ($weights['sekolah'] > 0) {
            if ($this->isGamingLaptop($laptop)) $score -= 10;
            if ($laptop->price > 10000000) $score -= 8;
            if (preg_match('/i7|i9|ryzen 7|ryzen 9/i', $laptop->cpu)) $score -= 5;
            if ($laptop->price < 8000000) $score += 5;
            if (preg_match('/i3|ryzen 3|pentium|celeron/i', $laptop->cpu)) $score += 3;
        }
        
        // Optimasi untuk konteks gaming
        if ($weights['gaming'] > 0) {
            if (!$this->isDedicatedGPU($laptop->gpu)) $score -= 10;
            if ($laptop->ram < 8) $score -= 8;
            if ($this->isDedicatedGPU($laptop->gpu)) $score += 5;
            if ($laptop->ram >= 16) $score += 3;
        }
        
        // Optimasi untuk konteks editing
        if ($weights['editing'] > 0) {
            if (!$this->isHighEndCPU($laptop->cpu)) $score -= 8;
            if ($laptop->ram < 16) $score -= 5;
            if ($this->isHighEndCPU($laptop->cpu)) $score += 5;
            if ($laptop->ram >= 32) $score += 3;
        }
        
        // Penanganan preferensi ketat CPU
        if (isset($weights['cpu_strict'])) {
            $laptopCpu = strtolower($laptop->cpu);
            $brand = $weights['cpu_brand'];
            
            if ($brand === 'amd') {
                if (strpos($laptopCpu, 'amd') !== false || strpos($laptopCpu, 'ryzen') !== false) {
                    $score += 15;
                } else {
                    $score -= 15;
                }
            }
            elseif ($brand === 'intel') {
                if (strpos($laptopCpu, 'intel') !== false || strpos($laptopCpu, 'core') !== false) {
                    $score += 15;
                } else {
                    $score -= 15;
                }
            }
        }
        
        // Penanganan preferensi ketat RAM
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
        
        // Penanganan preferensi ketat GPU
        if (isset($weights['gpu_strict'])) {
            $laptopGpu = strtolower($laptop->gpu);
            $gpuType = $weights['gpu_type'];
            
            if (strpos($laptopGpu, $gpuType) !== false) {
                $score += 10;
                
                // Validasi model GPU
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
        
        // Penanganan seri GPU
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

    /**
     * Mengekstrak skor model GPU
     * 
     * @param string $gpu Spesifikasi GPU
     * @param string $series Seri GPU (RTX, GTX)
     * @return int Skor numerik model GPU
     */
    private function extractGPUModelScore(string $gpu, string $series): int
    {
        $gpu = strtolower($gpu);
        $pattern = '/' . $series . '\s*(\d{4})/';
        
        if (preg_match($pattern, $gpu, $matches)) {
            return (int)$matches[1]; // Model yang lebih tinggi = skor lebih tinggi
        }
        
        return 0;
    }

    /**
     * Mengecek apakah GPU termasuk dedicated
     * 
     * @param string $gpu Spesifikasi GPU
     * @return bool True jika dedicated GPU
     */
    private function isDedicatedGPU(string $gpu): bool
    {
        return preg_match('/RTX \d{4}|GTX \d{4}|Radeon RX/i', $gpu);
    }

    /**
     * Mengecek apakah CPU termasuk high-end
     * 
     * @param string $cpu Spesifikasi CPU
     * @return bool True jika high-end CPU
     */
    private function isHighEndCPU(string $cpu): bool
    {
        return preg_match('/i[579]|Ryzen [79]/i', $cpu);
    }

    /**
     * Menghitung skor harga berdasarkan kedekatan dengan target
     * 
     * @param float $price Harga laptop
     * @param float $target Harga target
     * @return float Skor harga (0-5)
     */
    private function calculatePriceScore(float $price, float $target): float
    {
        $diff = abs($price - $target);
        $maxDiff = 2000000; // Batas maksimal perbedaan
        $normalizedDiff = min($diff, $maxDiff) / $maxDiff;
        return 5 * (1 - $normalizedDiff); // Semakin dekat, skor semakin tinggi
    }

    /**
     * Menghitung Term Frequency (TF)
     * 
     * @param array $terms Daftar term
     * @return array TF per term [term => nilai]
     */
    private function calculateTF(array $terms): array
    {
        $tf = [];
        $counts = array_count_values($terms);
        
        foreach ($counts as $term => $count) {
            $tf[$term] = $count > 0 ? (1 + log($count)) : 0;
        }
        
        return $tf;
    }

    /**
     * Menghitung Inverse Document Frequency (IDF)
     * 
     * @param Collection $documents Koleksi dokumen
     * @return array IDF per term
     */
    private function calculateIDF(Collection $documents): array
    {
        $idf = [];
        $totalDocs = $documents->count();
        $docFreq = [];
        
        // Hitung frekuensi dokumen per term
        foreach ($documents as $doc) {
            foreach (array_unique($doc) as $term) {
                $docFreq[$term] = ($docFreq[$term] ?? 0) + 1;
            }
        }
        
        // Hitung IDF
        foreach ($docFreq as $term => $df) {
            $idf[$term] = log(($totalDocs + 1) / ($df + 0.5)) + 1;
        }
        
        return $idf;
    }

    /**
     * Mengurutkan hasil dengan filter konteks
     * 
     * @param array $scores Skor per laptop
     * @param array $contextWeights Bobot konteks
     * @return Collection Rekomendasi terurut
     */
    private function getSortedResults(array $scores, array $contextWeights): Collection
    {
        return collect($scores)
            // Filter berdasarkan konteks
            ->filter(function ($score, $id) use ($contextWeights) {
                $laptop = Laptop::find($id);
                
                // Filter minimum RAM
                if ($laptop->ram < 4) return false;
                
                // Filter konteks kantor
                if ($contextWeights['kantor'] > 0) {
                    if ($this->isGamingLaptop($laptop)) return false;
                    if ($this->isDedicatedGPU($laptop->gpu)) return false;
                    if ($laptop->price > 15000000) return false;
                }
                
                // Filter konteks sekolah
                if ($contextWeights['sekolah'] > 0) {
                    if ($this->isGamingLaptop($laptop)) return false;
                    if ($laptop->price > 10000000) return false;
                    if (!preg_match('/i3|ryzen 3|pentium|celeron/i', strtolower($laptop->cpu))) return false;
                }
                
                // Filter konteks gaming
                if ($contextWeights['gaming'] > 0) {
                    if (!$this->isDedicatedGPU($laptop->gpu)) return false;
                    if ($laptop->ram < 8) return false;
                }
                
                // Filter konteks editing
                if ($contextWeights['editing'] > 0) {
                    if (!$this->isHighEndCPU($laptop->cpu)) return false;
                    if ($laptop->ram < 16) return false;
                }
                
                // Filter preferensi ketat CPU
                if (isset($contextWeights['cpu_strict'])) {
                    $laptopCpu = strtolower($laptop->cpu);
                    $brand = $contextWeights['cpu_brand'];
                    
                    if ($brand === 'amd') {
                        if (!preg_match('/(amd|ryzen)/', $laptopCpu)) return false;
                    }
                    elseif ($brand === 'intel') {
                        if (!preg_match('/(intel|core)/', $laptopCpu)) return false;
                    }
                }
                
                // Filter preferensi ketat RAM
                if (isset($contextWeights['ram_strict'])) {
                    $ramSize = $contextWeights['ram_size'];
                    if ($laptop->ram < $ramSize) return false;
                    if ($laptop->ram > $ramSize * 1.5) return false;
                }
                
                // Filter preferensi ketat GPU
                if (isset($contextWeights['gpu_strict'])) {
                    $laptopGpu = strtolower($laptop->gpu);
                    $gpuType = $contextWeights['gpu_type'];
                    
                    if (!str_contains($laptopGpu, $gpuType)) return false;
                    
                    if (isset($contextWeights['gpu_model'])) {
                        $model = $contextWeights['gpu_model'];
                        if (!preg_match("/{$gpuType}.*{$model}/", $laptopGpu)) return false;
                    }
                }
                
                // Filter seri GPU
                if (isset($contextWeights['gpu_series'])) {
                    $laptopGpu = strtolower($laptop->gpu);
                    $series = $contextWeights['gpu_series'];
                    if (!str_contains($laptopGpu, $series)) return false;
                }
                
                return $score > 0;
            })
            // Urutkan dengan penyesuaian akhir
            ->sortByDesc(function ($score, $id) use ($contextWeights) {
                $laptop = Laptop::find($id);
                $finalScore = $score;
                
                // Tambahan poin untuk konteks
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
                
                // Tambahan poin untuk spesifikasi ketat
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
                    if ($laptop->ram == $ramSize) $finalScore += 5;
                }
                
                if (isset($contextWeights['gpu_series']) && !isset($contextWeights['gpu_model'])) {
                    $series = $contextWeights['gpu_series'];
                    $modelScore = $this->extractGPUModelScore($laptop->gpu, $series);
                    $finalScore += $modelScore * 0.1;
                    
                    if ($series === 'rtx') $finalScore += 5;
                }
                
                return $finalScore;
            })
            // Ambil top 20
            ->take(20)
            ->keys()
            // Ambil data laptop lengkap
            ->pipe(function ($ids) {
                return Laptop::whereIn('id', $ids)
                    ->with('brand')
                    ->get();
            });
    }

    /**
     * Mengecek apakah laptop termasuk kategori gaming
     * 
     * @param Laptop $laptop Model laptop
     * @return bool True jika gaming laptop
     */
    private function isGamingLaptop(Laptop $laptop): bool
    {
        $text = strtolower($laptop->brand->name . ' ' . $laptop->series . ' ' . $laptop->model);
        return preg_match('/rtx|gtx|rog|alienware|predator|legion|gaming|msi|razer|geforce|rxt|tuf|nitro|omen|ge66|ge76|gs66|gl63|gp66|strix|scar|aorus|eluktronics|ideapad gaming|nitro|helios|trident|mech|vector|bravo|delta|alpha|beta/i', $text);
    }

    /**
     * Mengecek apakah kalimat bersifat negatif
     * 
     * @param string $text Teks review
     * @return bool True jika negatif
     */
    private function isNegativeSentence(string $text): bool
    {
        $indicators = [
            'hanya bisa untuk', 'kurang cocok untuk', 'tidak cocok untuk', 
            'tidak kuat untuk', 'tidak bagus untuk', 'kurang bagus buat',
            'tidak direkomendasikan untuk', 'lemot saat', 'kurang nyaman untuk',
            'kurang optimal buat', 'tidak support', 'sering lag', 
            'cepat panas saat', 'frame drop saat'
        ];
        
        foreach ($indicators as $phrase) {
            if (str_contains($text, $phrase)) return true;
        }
        
        return false;
    }

    /**
     * Mengecek apakah kalimat bersifat positif
     * 
     * @param string $text Teks review
     * @return bool True jika positif
     */
    private function isPositiveSentence(string $text): bool
    {
        $indicators = [
            'cocok untuk', 'bagus untuk', 'sangat baik untuk', 
            'lancar digunakan untuk', 'mendukung untuk', 'powerful untuk',
            'ideal untuk', 'terbaik untuk', 'mantap buat', 'oke buat',
            'recommended buat', 'responsif saat', 'mulus saat digunakan'
        ];
        
        foreach ($indicators as $phrase) {
            if (str_contains($text, $phrase)) return true;
        }
        
        return false;
    }

    /**
     * Mengecek ketidaksesuaian review dengan spesifikasi
     * 
     * @param string $reviewText Teks review
     * @param Laptop $laptop Model laptop
     * @return bool True jika tidak sesuai
     */
    private function reviewMismatchWithSpecs(string $reviewText, Laptop $laptop): bool
    {
        $reviewText = strtolower($reviewText);
        
        // Review editing tapi spesifikasi rendah
        if (str_contains($reviewText, 'bagus untuk editing') && 
            (!$this->isHighEndCPU($laptop->cpu) || $laptop->ram < 16)) {
            return true;
        }
        
        // Review gaming tapi spesifikasi rendah
        if (str_contains($reviewText, 'lancar untuk gaming') && 
            (!$this->isDedicatedGPU($laptop->gpu) || $laptop->ram < 8)) {
            return true;
        }
        
        // Review kantor tapi laptop gaming
        if (str_contains($reviewText, 'bagus untuk kerja kantor') && 
            ($this->isGamingLaptop($laptop) || $laptop->ram < 8)) {
            return true;
        }
        
        // Review sekolah tapi harga mahal
        if (str_contains($reviewText, 'bagus untuk sekolah') && 
            ($laptop->ram < 4 || $laptop->price > 10000000)) {
            return true;
        }
        
        return false;
    }

    /**
     * Mendapatkan daftar stopword bahasa Indonesia
     * 
     * @return array Daftar stopword
     */
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

    /**
     * Mendapatkan pemetaan sinonim
     * 
     * @return array Pemetaan [term => sinonim]
     */
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