<?php
// app/Models/Trade.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Trade extends Model
{
    protected $fillable = [
        'product_id',
        'market',
        'counterparty',
        'price_per_kg',
        'quantity_kg',
        'trade_date',
    ];

    protected $casts = [
        'price_per_kg' => 'decimal:4',
        'quantity_kg' => 'decimal:4',
        'total_value' => 'decimal:4',
        'trade_date' => 'date',
    ];

    /**
     * Get the product that owns the trade.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope to get the latest trade per product by trade_date within an
     * optional date range. Uses a subquery that first filters by date range,
     * finds the MAX(trade_date) per product, then resolves ties via MAX(id).
     *
     * Without a date range → latest trade ever per product.
     * With a date range    → latest trade within that range per product.
     */
    public function scopeLatestPerProduct(
        $query,
        ?string $from = null,
        ?string $to = null
    ) {
        // Step 1: find the latest trade_date per product within the range
        $latestDateSub = DB::table('trades')
            ->selectRaw('product_id, MAX(trade_date) as max_date')
            ->when($from, fn ($q) => $q->whereDate('trade_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('trade_date', '<=', $to))
            ->groupBy('product_id');

        // Step 2: among rows that share the same (product_id, max_date),
        // pick the one with the highest id to break ties deterministically
        $latestIdSub = DB::table('trades as t')
            ->selectRaw('MAX(t.id) as max_id')
            ->joinSub($latestDateSub, 'ld', function ($join) {
                $join->on('t.product_id', '=', 'ld.product_id')
                     ->on(DB::raw('DATE(t.trade_date)'), '=', DB::raw('DATE(ld.max_date)'));
            })
            ->groupBy('t.product_id');

        return $query->whereIn('id', $latestIdSub);
    }
}