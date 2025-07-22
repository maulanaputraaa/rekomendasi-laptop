<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

abstract class Controller
{
    //
    public function somePage()
    {
        return Inertia::render('UploadData', [
            'auth' => [
                'user' => \Illuminate\Support\Facades\Auth::user(),
            ],
        ]);
    }
}
