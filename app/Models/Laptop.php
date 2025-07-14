<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Laptop extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id', 'series', 'model', 'cpu', 'ram', 'storage', 'gpu', 'price'
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

    public function laptopPrices()
    {
        return $this->hasMany(LaptopPrice::class);
    }

    // Method untuk mendapatkan harga rata-rata
    public function getAveragePriceAttribute()
    {
        return $this->laptopPrices()->avg('price') ?? $this->price;
    }

    // Method untuk mendapatkan harga terbaru
    public function getLatestPriceAttribute()
    {
        return $this->laptopPrices()->latest()->first()?->price ?? $this->price;
    }

    // Method untuk mendapatkan rentang harga
    public function getPriceRangeAttribute()
    {
        $prices = $this->laptopPrices()->pluck('price');
        if ($prices->isEmpty()) {
            return ['min' => $this->price, 'max' => $this->price];
        }
        return [
            'min' => $prices->min(),
            'max' => $prices->max()
        ];
    }
}
