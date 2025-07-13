<?php

namespace App\Http\Controllers;

use App\Models\Laptop;
use App\Models\Brand;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Ambil semua laptop dengan relasi yang diperlukan
        $laptops = Laptop::with(['reviews', 'brand'])->get();
        
        // Format data laptop sesuai kebutuhan frontend
        $formattedLaptops = $laptops->map(function($laptop) {
            $averageRating = $laptop->reviews->avg('rating');
            
            return [
                'id' => $laptop->id,
                'name' => $laptop->name,
                'brand' => $laptop->brand->name ?? 'Unknown',
                'series' => $laptop->series,
                'model' => $laptop->model,
                'price' => 'Rp ' . number_format($laptop->price, 0, ',', '.'),
                'numeric_price' => (int) $laptop->price,
                'average_rating' => $averageRating ? round($averageRating, 1) : 'No rating',
                'processor' => $laptop->processor,
            ];
        });

        // Ambil semua brand yang unik
        $brands = Brand::pluck('name')->unique()->toArray();

        return inertia('dashboard', [
            'laptops' => $formattedLaptops,
            'brands' => $brands,
        ]);
    }
}