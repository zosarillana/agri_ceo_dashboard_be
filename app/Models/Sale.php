<?php

// app/Models/Sale.php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use Auditable;

    protected $fillable = [
        'product_id',
        'market',
        'sales',
        'asp_per_kg',
        'quantity_kg',
        'sale_date',
    ];

    protected $casts = [
        'sales' => 'decimal:4',
        'asp_per_kg' => 'decimal:4',
        'asp_total_usd' => 'decimal:4',
        'quantity_kg' => 'decimal:4',
        'total_sales_usd' => 'decimal:4',
        'sale_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope to get the latest sale per product by sale_date within an
     * optional date range. Uses a subquery that first filters by date range,
     * finds the MAX(sale_date) per product, then resolves ties via MAX(id).
     *
     * Without a date range → latest sale ever per product.
     * With a date range    → latest sale within that range per product.
     */
    public function scopeLatestPerProduct(
        $query,
        ?string $from = null,
        ?string $to = null
    ) {
        // Step 1: find the latest sale_date per product within the range
        $latestDateSub = \DB::table('sales')
            ->selectRaw('product_id, MAX(sale_date) as max_date')
            ->when($from, fn ($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('sale_date', '<=', $to))
            ->groupBy('product_id');

        // Step 2: among rows that share the same (product_id, max_date),
        // pick the one with the highest id to break ties deterministically
        $latestIdSub = \DB::table('sales as s')
            ->selectRaw('MAX(s.id) as max_id')
            ->joinSub($latestDateSub, 'ld', function ($join) {
                $join->on('s.product_id', '=', 'ld.product_id')
                    ->on(\DB::raw('DATE(s.sale_date)'), '=', \DB::raw('DATE(ld.max_date)'));
            })
            ->groupBy('s.product_id');

        return $query->whereIn('id', $latestIdSub);
    }
}