<?php
// app/Services/SaleService.php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SaleService
{
    /**
     * Smart bulk save: upsert by (product_id, sale_date).
     *
     * @param  array<int, array{product_id: int, market: string, asp_per_kg: float, quantity_kg: float}>  $rows
     * @param  string|null  $saleDate  Y-m-d, defaults to today
     */
    public function storeBulk(array $rows, ?string $saleDate = null): Collection
    {
        return DB::transaction(function () use ($rows, $saleDate) {
            $date  = $saleDate
                ? Carbon::parse($saleDate)->toDateString()
                : Carbon::today()->toDateString();
            $sales = collect();

            foreach ($rows as $row) {
                $existing = Sale::where('product_id', $row['product_id'])
                    ->whereDate('sale_date', $date)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'market'      => $row['market'],
                        'asp_per_kg'  => $row['asp_per_kg'],
                        'quantity_kg' => $row['quantity_kg'],
                    ]);
                    $sales->push($existing->fresh());
                } else {
                    $sales->push(Sale::create([
                        'product_id'  => $row['product_id'],
                        'market'      => $row['market'],
                        'asp_per_kg'  => $row['asp_per_kg'],
                        'quantity_kg' => $row['quantity_kg'],
                        'sale_date'   => $date,
                    ]));
                }
            }

            return $sales;
        });
    }

    /**
     * Latest sale per product, optionally filtered to a date range.
     */
    public function getLatest(?string $from = null, ?string $to = null): Collection
    {
        return Sale::with('product')
            ->latestPerProduct($from, $to)
            ->orderBy('product_id')
            ->get();
    }

    /**
     * Summary totals for the matching sales.
     */
    public function getSummary(?string $from = null, ?string $to = null): array
    {
        $latest = $this->getLatest($from, $to);

        return [
            'total_sales_usd'   => (float) $latest->sum('total_sales_usd'),
            'total_quantity_kg' => (float) $latest->sum('quantity_kg'),
            'export_count'      => $latest->where('market', 'Export')->count(),
            'local_count'       => $latest->where('market', 'Local')->count(),
            'from'              => $from,
            'to'                => $to,
        ];
    }
}