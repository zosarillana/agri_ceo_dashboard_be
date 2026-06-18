<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Procurement extends Model
{
    use Auditable;
    protected $fillable = [
        'product_id',
        'item_name',
        'supplier',
        'quantity',
        'unit',
        'status',
        'procurement_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'procurement_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate('procurement_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('procurement_date', '<=', $to));
    }
}