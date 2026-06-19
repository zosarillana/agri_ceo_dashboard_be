<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradeItem extends Model
{
    use Auditable;
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