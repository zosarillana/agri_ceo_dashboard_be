<?php
// app/Models/EnergyRecord.php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class EnergyRecord extends Model
{
    use Auditable;
    protected $fillable = [
        'account',
        'billing_month',
        'kw',
        'demand',
        'billed_amount',
    ];

    protected $casts = [
        'kw'             => 'decimal:2',
        'demand'         => 'decimal:2',
        'billed_amount'  => 'decimal:2',
        'billing_month'  => 'date:Y-m',
    ];

    /**
     * Get all records grouped by account.
     */
    public function scopeGrouped($query)
    {
        return $query
            ->orderBy('billing_month')
            ->orderBy('account');
    }
}