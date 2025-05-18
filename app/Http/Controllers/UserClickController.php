<?php

namespace App\Http\Controllers;

use App\Models\UserClick;
use App\Models\Laptop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserClickController extends Controller
{
    /**
     * Catat klik user terhadap laptop tertentu.
     */
    public function recordClick($laptopId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $laptop = Laptop::findOrFail($laptopId);
        $brandId = $laptop->brand_id;

        // Cek apakah sudah pernah klik sebelumnya
        $click = UserClick::where('user_id', $user->id)
                        ->where('brand_id', $brandId)
                        ->first();

        if ($click) {
            $click->increment('click_count');
        } else {
            UserClick::create([
                'user_id' => $user->id,
                'brand_id' => $brandId,
                'click_count' => 1,
            ]);
        }

        return response()->json(['message' => 'Click recorded successfully']);
    }
}
