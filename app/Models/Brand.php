<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function laptops()
    {
        return $this->hasMany(Laptop::class);
    }

    public function userClicks()
    {
        return $this->hasMany(UserClick::class);
    }
}

