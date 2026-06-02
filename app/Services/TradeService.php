<?php
// app/Services/TradeService.php

namespace App\Services;

use App\Models\Trade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TradeService
{
    /**
     * Smart bulk save: upsert by (product_id, trade_date).
     *
     * @param  array<int, array{product_id: int, market: string, counterparty: string|null, price_per_kg: float, quantity_kg: float}>  $rows
     * @param  string|null  $tradeDate  Y-m-d, defaults to today
     */
    public function storeBulk(array $rows, ?string $tradeDate = null): Collection
    {
        $date = $tradeDate
            ? Carbon::parse($tradeDate)->toDateString()
            : Carbon::today()->toDateString();

        // Prepare data
        $data = array_map(function ($row) use ($date) {
            return [
                'product_id' => $row['product_id'],
                'market' => $row['market'],
                'counterparty' => $row['counterparty'] ?? null,
                'price_per_kg' => $row['price_per_kg'],
                'quantity_kg' => $row['quantity_kg'],
                'trade_date' => $date,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $rows);

        // Single query upsert
        Trade::upsert(
            $data,
            ['product_id', 'trade_date'],  // Unique constraint columns
            ['market', 'counterparty', 'price_per_kg', 'quantity_kg']  // Columns to update
        );

        // Retrieve the affected records
        return Trade::with('product')
            ->where('trade_date', $date)
            ->whereIn('product_id', array_column($rows, 'product_id'))
            ->get();
    }

    /**
     * Latest trade per product, optionally filtered to a date range.
     */
    public function getLatest(?string $from = null, ?string $to = null): Collection
    {
        return Trade::with('product')
            ->latestPerProduct($from, $to)
            ->orderBy('product_id')
            ->get();
    }

    /**
     * Summary totals for the matching trades.
     */
    public function getSummary(?string $from = null, ?string $to = null): array
    {
        $latest = $this->getLatest($from, $to);

        return [
            'total_volume' => (float) $latest->sum('quantity_kg'),
            'total_value' => (float) $latest->sum('total_value'),
            'avg_price' => $latest->sum('quantity_kg') > 0 
                ? (float) ($latest->sum('total_value') / $latest->sum('quantity_kg'))
                : 0,
            'total_orders' => $latest->count(),
            'export_orders' => $latest->where('market', 'Export')->count(),
            'local_orders' => $latest->where('market', 'Local')->count(),
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Delete a trade by ID.
     */
    public function deleteTrade(int $id): bool
    {
        $trade = Trade::findOrFail($id);
        return $trade->delete();
    }

    /**
     * Update a single trade.
     */
    public function updateTrade(int $id, array $data): Trade
    {
        $trade = Trade::findOrFail($id);
        
        $trade->update([
            'market' => $data['market'],
            'counterparty' => $data['counterparty'] ?? null,
            'price_per_kg' => $data['price_per_kg'],
            'quantity_kg' => $data['quantity_kg'],
        ]);
        
        return $trade->load('product');
    }
}