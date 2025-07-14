<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LaptopPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'laptop_id', 'price', 'source'
    ];

    protected $casts = [
        'price' => 'decimal:2'
    ];

    public function laptop()
    {
        return $this->belongsTo(Laptop::class);
    }
}
