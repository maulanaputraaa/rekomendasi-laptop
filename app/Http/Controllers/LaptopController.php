<?php

namespace App\Http\Controllers;

use App\Models\Laptop;
use App\Models\UserClick;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class LaptopController extends Controller
{
    public function show($id)
    {
        $laptop = Laptop::with(['brand', 'reviews'])->findOrFail($id);

        if (Auth::check()) {
            $user = Auth::user();
            if ($user->role !== 'admin') {
                $brandId = $laptop->brand->id;
                UserClick::updateOrCreate(
                    ['user_id' => $user->id, 'brand_id' => $brandId],
                    ['click_count' => DB::raw('click_count + 1')]
                );
            }
        }
        return inertia('Laptop/LaptopDetail', [
            'laptop' => [
                'id' => $laptop->id,
                'brand' => $laptop->brand->name,
                'series' => $laptop->series,
                'model' => $laptop->model,
                'cpu' => $laptop->cpu,
                'gpu' => $laptop->gpu,
                'ram' => $laptop->ram,
                'storage' => $laptop->storage,
                'price' => $laptop->price,
                'average_rating' => round($laptop->reviews->avg('rating') ?? 0, 1),
                'reviews' => $laptop->reviews->map(function ($rev) {
                    return [
                        'id' => $rev->id,
                        'responder_name' => $rev->responder_name,
                        'rating' => $rev->rating,
                        'review' => $rev->review,
                        'created_at' => $rev->created_at->diffForHumans(),
                    ];
                }),
            ]
        ]);
    }

    public function index()
    {
        return Laptop::with('brand')->get();
    }

    public function destroy(Laptop $laptop)
    {
        try {
            $laptop->delete();
            return redirect()->back()->with('success', 'Laptop berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus laptop');
        }
    }
}
