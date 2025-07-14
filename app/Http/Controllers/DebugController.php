<?php

namespace App\Http\Controllers;

use App\Models\Laptop;
use App\Models\Brand;
use Illuminate\Http\Request;

class DebugController extends Controller
{
    public function laptopCount()
    {
        $totalLaptops = Laptop::count();
        $totalBrands = Brand::count();
        
        $laptops = Laptop::with('brand')->get();
        
        $laptopData = $laptops->map(function($laptop) {
            return [
                'id' => $laptop->id,
                'series' => $laptop->series,
                'model' => $laptop->model,
                'brand' => $laptop->brand->name ?? 'No Brand',
                'price' => $laptop->price,
            ];
        });
        
        return response()->json([
            'total_laptops' => $totalLaptops,
            'total_brands' => $totalBrands,
            'laptops' => $laptopData,
            'message' => 'Debug data for laptop count issue'
        ]);
    }
    
    public function dashboardDebug()
    {
        // Sama seperti DashboardController tapi return JSON
        $laptops = Laptop::with(['reviews', 'brand'])->get();
        
        $formattedLaptops = $laptops->map(function($laptop) {
            $averageRating = $laptop->reviews->avg('rating');
            
            return [
                'id' => $laptop->id,
                'name' => $laptop->series . ' ' . $laptop->model,
                'brand' => $laptop->brand->name ?? 'Unknown',
                'series' => $laptop->series,
                'model' => $laptop->model,
                'price' => 'Rp ' . number_format($laptop->price, 0, ',', '.'),
                'numeric_price' => (int) $laptop->price,
                'average_rating' => $averageRating ? round($averageRating, 1) : 'No rating',
                'cpu' => $laptop->cpu,
                'ram' => $laptop->ram,
                'storage' => $laptop->storage,
                'gpu' => $laptop->gpu,
            ];
        });
        
        return response()->json([
            'total_count' => $laptops->count(),
            'formatted_count' => $formattedLaptops->count(),
            'laptops' => $formattedLaptops,
            'sample_laptops' => $formattedLaptops->take(3)
        ]);
    }
}
