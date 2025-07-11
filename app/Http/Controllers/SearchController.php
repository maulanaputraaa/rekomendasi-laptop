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
        if (trim($query) === '') {
            return redirect()->back()->with('error', 'Masukkan kata kunci pencarian.');
        }
        $results = $this->searchService->searchWithTFIDF($query);
        $sortedResults = $results->sortBy('harga');
        return inertia('Laptop/SearchResult', [
            'query' => $query,
            'results' => $sortedResults
        ]);
    }
}