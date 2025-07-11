<?php
namespace App\Http\Controllers;

use App\Models\Laptop;
use App\Models\User;
use App\Models\Brand;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_laptops' => Laptop::count(),
            'total_users' => User::count(),
            'total_brands' => Brand::count(),
            'avg_price' => Laptop::avg('price') ?? 0,
        ];
        return Inertia::render('Admin/DashboardAdmin', [
            'stats' => $stats, // Pastikan ini ada
            'laptops' => Laptop::with('brand')->take(null)->get(),
            'users' => User::take(null)->get()
        ]);
    }
}