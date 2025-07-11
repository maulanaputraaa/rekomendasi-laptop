<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'responder_name' => 'required|string',
            'laptop_id' => 'required|exists:laptops,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);
        return Review::create($request->all());
    }
}
