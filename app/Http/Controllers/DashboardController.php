<?php

namespace App\Http\Controllers;

use App\Models\Laptop;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        // Ambil semua laptop dengan relasi yang diperlukan
        $laptops = Laptop::with(['reviews', 'brand'])->get();

        // Format data laptop sesuai kebutuhan frontend
        $formattedLaptops = $laptops->map(function ($laptop) {
            $averageRating = $laptop->reviews->avg('rating');

            return [
                'id' => $laptop->id,
                'name' => $laptop->series . ' ' . $laptop->model, // Gabungkan series dan model
                'brand' => $laptop->brand->name ?? 'Unknown',
                'series' => $laptop->series,
                'model' => $laptop->model,
                'price' => 'Rp ' . number_format($laptop->price, 0, ',', '.'),
                'numeric_price' => (int) $laptop->price,
                'average_rating' => $averageRating ? round($averageRating, 1) : 'No rating',
                'cpu' => $laptop->cpu, // Ganti processor dengan cpu
                'ram' => $laptop->ram,
                'storage' => $laptop->storage,
                'gpu' => $laptop->gpu,
            ];
        });

        // Ambil semua brand yang unik
        $brands = Brand::pluck('name')->unique()->toArray();

        // Debug: Log jumlah laptop
        Log::info('Dashboard laptops count', [
            'total_laptops' => $laptops->count(),
            'formatted_laptops' => $formattedLaptops->count()
        ]);

        return inertia('dashboard', [
            'laptops' => $formattedLaptops,
            'brands' => $brands,
        ]);
    }
}
