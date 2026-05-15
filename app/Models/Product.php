<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'unit',
        'default_target',
        'is_active',
    ];

    protected $casts = [
        'default_target' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function productionEntries()
    {
        return $this->hasMany(ProductionEntry::class);
    }
}