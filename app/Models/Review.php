<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Review extends Model
{
    use HasFactory;

    protected $fillable = ['responder_name', 'laptop_id', 'rating', 'review'];

    public function laptop()
    {
        return $this->belongsTo(Laptop::class);
    }
}
