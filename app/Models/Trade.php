<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Trade extends Model
{
    use Auditable;
    protected $fillable = [
        'trade_item_id',
        'market',
        'counterparty',
        'input_kg',
        'output_kg',
        'trade_date',
    ];

    protected $casts = [
        'input_kg'    => 'decimal:4',
        'output_kg'   => 'decimal:4',
        'total_value' => 'decimal:4',
        'trade_date'  => 'date',
    ];

    public function tradeItem(): BelongsTo
    {
        return $this->belongsTo(TradeItem::class);
    }

    /**
     * Latest trade per trade item within optional date range.
     */
    public function scopeLatestPerTradeItem(
        $query,
        ?string $from = null,
        ?string $to = null
    ) {
        $latestDateSub = DB::table('trades')
            ->selectRaw('trade_item_id, MAX(trade_date) as max_date')
            ->when($from, fn ($q) => $q->whereDate('trade_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('trade_date', '<=', $to))
            ->groupBy('trade_item_id');

        $latestIdSub = DB::table('trades as t')
            ->selectRaw('MAX(t.id) as max_id')
            ->joinSub($latestDateSub, 'ld', function ($join) {
                $join->on('t.trade_item_id', '=', 'ld.trade_item_id')
                     ->on(DB::raw('DATE(t.trade_date)'), '=', DB::raw('DATE(ld.max_date)'));
            })
            ->groupBy('t.trade_item_id');

        return $query->whereIn('id', $latestIdSub);
    }
}