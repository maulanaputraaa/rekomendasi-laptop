<?php

namespace App\Http\Controllers;

use App\Models\Laptop;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Ambil laptop beserta rata-rata ratingnya dan brand-nya
        $laptops = Laptop::with(['reviews', 'brand']) // Mengambil relasi reviews dan brand
                        ->get()
                        ->map(function($laptop) {
                            // Menambahkan informasi tambahan pada laptop
                            $laptop->average_rating = $laptop->reviews->avg('rating'); // Menghitung rata-rata rating
                            return [
                                'id' => $laptop->id,
                                'name' => $laptop->series . ' ' . $laptop->model, // Menggabungkan series dan model menjadi nama laptop
                                'brand' => $laptop->brand->name ?? 'Tidak diketahui', // Nama brand, jika tidak ada maka 'Tidak diketahui'
                                'price' => 'Rp ' . number_format($laptop->price, 0, ',', '.'), // Format harga
                                'average_rating' => $laptop->average_rating ?: 'Belum ada rating', // Jika rata-rata rating tidak ada, tampilkan "Belum ada rating"
                            ];
                        });

        // Mengirim data ke tampilan dashboard
        return inertia('dashboard', [
            'laptops' => $laptops,
        ]);
    }
}
