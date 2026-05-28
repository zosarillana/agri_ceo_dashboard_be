<?php
// app/Models/QcRecord.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class QcRecord extends Model
{
    protected $fillable = [
        'product_id',
        'tested',
        'passed',
        'qc_date',
    ];

    protected $casts = [
        'tested'           => 'integer',
        'passed'           => 'integer',
        'failed'           => 'integer',
        'pass_rate'        => 'decimal:4',
        'rejection_rate'   => 'decimal:4',
        'qc_date'          => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Latest QC record per product, optionally filtered to a date range.
     * Same two-step subquery pattern as Sale::scopeLatestPerProduct.
     */
    public function scopeLatestPerProduct(
        $query,
        ?string $from = null,
        ?string $to   = null
    ) {
        // Step 1: latest qc_date per product within range
        $latestDateSub = DB::table('qc_records')
            ->selectRaw('product_id, MAX(qc_date) as max_date')
            ->when($from, fn ($q) => $q->whereDate('qc_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('qc_date', '<=', $to))
            ->groupBy('product_id');

        // Step 2: break ties by highest id
        $latestIdSub = DB::table('qc_records as q')
            ->selectRaw('MAX(q.id) as max_id')
            ->joinSub($latestDateSub, 'ld', function ($join) {
                $join->on('q.product_id', '=', 'ld.product_id')
                     ->on(DB::raw('DATE(q.qc_date)'), '=', DB::raw('DATE(ld.max_date)'));
            })
            ->groupBy('q.product_id');

        return $query->whereIn('id', $latestIdSub);
    }
}