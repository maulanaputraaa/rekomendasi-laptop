<?php
namespace App\Http\Controllers;

use App\Models\Laptop;
use App\Models\User;
use App\Models\Brand;
use Inertia\Inertia;

class AdminController extends Controller
{
    /**
     * Menampilkan dashboard admin dengan statistik dan data penting
     * 
     * Fitur utama:
     * 1. Statistik ringkas (jumlah laptop, pengguna, brand, harga rata-rata)
     * 2. Daftar laptop terbaru
     * 3. Daftar pengguna terbaru
     * 
     * @return \Inertia\Response
     */
    public function dashboard()
    {
        // Hitung statistik utama
        $stats = [
            'total_laptops' => Laptop::count(),
            'total_users' => User::count(),
            'total_brands' => Brand::count(),
            'avg_price' => Laptop::avg('price') ?? 0,
        ];

        // Ambil data untuk ditampilkan di dashboard
        $laptops = Laptop::with(['brand'])->latest()->get();

        return Inertia::render('Admin/DashboardAdmin', [
            'stats' => $stats,
            'laptops' => $laptops,
            'users' => User::latest()->take(8)->get()
        ]);
    }
}