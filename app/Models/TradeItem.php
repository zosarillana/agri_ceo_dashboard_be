<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradeItem extends Model
{
    protected $fillable = [
        'name',
        'code',
        'input',
        'output',
        'market',
    ];

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }
}