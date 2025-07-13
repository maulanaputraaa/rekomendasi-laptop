<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SearchService;

class SearchController extends Controller
{
    protected $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function search(Request $request)
    {
        $query = $request->input('query', '');
        $brands = $request->input('brands', '');
        $ram = $request->input('ram', '');
        $usage = $request->input('usage', '');
        $processor = $request->input('processor', '');
        $priceMin = $request->input('price_min', 0);
        $priceMax = $request->input('price_max', 50000000);

        // Jika tidak ada query dan tidak ada filter, redirect back
        if (trim($query) === '' && !$brands && !$ram && !$usage && !$processor && $priceMin == 0 && $priceMax == 50000000) {
            return redirect()->back()->with('error', 'Masukkan kata kunci pencarian atau pilih filter.');
        }

        // Gabungkan query dengan filter untuk membuat query pencarian yang lengkap
        $searchQuery = $this->buildSearchQuery($query, $brands, $ram, $usage, $processor, $priceMin, $priceMax);
        
        $results = $this->searchService->searchWithTFIDF($searchQuery);
        $sortedResults = $results->sortBy('harga');
        
        return inertia('Laptop/SearchResult', [
            'query' => $query,
            'results' => $sortedResults,
            'filters' => [
                'brands' => $brands,
                'ram' => $ram,
                'usage' => $usage,
                'processor' => $processor,
                'price_min' => $priceMin,
                'price_max' => $priceMax,
            ]
        ]);
    }

    /**
     * Membangun query pencarian yang menggabungkan query teks dengan filter
     */
    private function buildSearchQuery($query, $brands, $ram, $usage, $processor, $priceMin, $priceMax)
    {
        $searchTerms = [];
        
        // Tambahkan query teks jika ada
        if (trim($query)) {
            $searchTerms[] = trim($query);
        }
        
        // Tambahkan filter sebagai bagian dari query
        if ($brands) {
            $brandList = explode(',', $brands);
            $searchTerms = array_merge($searchTerms, $brandList);
        }
        
        if ($ram) {
            $ramList = explode(',', $ram);
            $searchTerms = array_merge($searchTerms, $ramList);
        }
        
        if ($usage) {
            $usageList = explode(',', $usage);
            // Konversi kebutuhan ke kata kunci yang relevan
            foreach ($usageList as $use) {
                switch (strtolower($use)) {
                    case 'gaming':
                        $searchTerms[] = 'gaming rtx gtx nvidia asus rog msi gaming';
                        break;
                    case 'kantor':
                        $searchTerms[] = 'office bisnis productivity intel i5 i3';
                        break;
                    case 'desain grafis':
                        $searchTerms[] = 'design graphic nvidia rtx creative workstation';
                        break;
                    case 'programming':
                        $searchTerms[] = 'programming development coding intel i7 i9 ryzen';
                        break;
                    case 'multimedia':
                        $searchTerms[] = 'multimedia video editing creator content';
                        break;
                    case 'pelajar':
                    case 'sekolah':
                        $searchTerms[] = 'student budget ekonomis murah';
                        break;
                    default:
                        $searchTerms[] = $use;
                }
            }
        }
        
        if ($processor) {
            $processorList = explode(',', $processor);
            foreach ($processorList as $proc) {
                switch (strtolower(trim($proc))) {
                    case 'intel':
                        $searchTerms[] = 'intel i3 i5 i7 i9 core processor';
                        break;
                    case 'amd':
                        $searchTerms[] = 'amd ryzen 3 5 7 9 processor';
                        break;
                    default:
                        $searchTerms[] = $proc;
                }
            }
        }
        
        // Tambahkan rentang harga sebagai bagian dari query
        if ($priceMin > 0 || $priceMax < 50000000) {
            $searchTerms[] = "harga:{$priceMin}-{$priceMax}";
        }
        
        // Gabungkan semua term menjadi satu query
        return implode(' ', $searchTerms);
    }
}