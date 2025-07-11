<?php

namespace App\Http\Controllers;

use App\Models\Laptop;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $laptops = Laptop::with(['reviews', 'brand'])
                        ->get()
                        ->map(function($laptop) {
                            $laptop->average_rating = $laptop->reviews->avg('rating');
                            return [
                                'id' => $laptop->id,
                                'name' => $laptop->series . ' ' . $laptop->model,
                                'brand' => $laptop->brand->name ?? 'Tidak diketahui',
                                'price' => 'Rp ' . number_format($laptop->price, 0, ',', '.'),
                                'average_rating' => $laptop->average_rating ?: 'Belum ada rating',
                            ];
                        });
        return inertia('dashboard', [
            'laptops' => $laptops,
        ]);
    }
}
