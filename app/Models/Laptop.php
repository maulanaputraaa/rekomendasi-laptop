<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Laptop extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'series',
        'model',
        'cpu',
        'ram',
        'storage',
        'gpu',
        'price'
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function userClicks()
    {
        return $this->hasMany(UserClick::class);
    }
}
