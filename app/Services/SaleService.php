<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SaleService
{
    /**
     * Smart bulk save: upsert by (product_id, sale_date).
     */
    public function storeBulk(array $rows, ?string $saleDate = null): Collection
    {
        $date = $saleDate
            ? Carbon::parse($saleDate)->toDateString()
            : Carbon::today()->toDateString();

        $data = array_map(function ($row) use ($date) {
            return [
                'product_id' => $row['product_id'],
                'market' => $row['market'],
                'asp_per_kg' => $row['asp_per_kg'],
                'quantity_kg' => $row['quantity_kg'],
                'sale_date' => $date,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $rows);

        Sale::upsert(
            $data,
            ['product_id', 'sale_date'],
            ['market', 'asp_per_kg', 'quantity_kg']
        );

        return Sale::where('sale_date', $date)
            ->whereIn('product_id', array_column($rows, 'product_id'))
            ->get();
    }

    /**
     * Latest sale per product, optionally filtered to a date range.
     * Defaults to current month if no dates provided.
     */
    public function getLatest(?string $from = null, ?string $to = null): Collection
    {
        // Default to current month if no date range provided
        if (! $from && ! $to) {
            $from = Carbon::now()->startOfMonth()->toDateString();
            $to = Carbon::now()->endOfMonth()->toDateString();
        }

        return Sale::with('product')
            ->latestPerProduct($from, $to)
            ->orderBy('product_id')
            ->get();
    }

    /**
     * Summary totals for the matching sales.
     * Defaults to current month if no dates provided.
     */
    // app/Services/SaleService.php

    // app/Services/SaleService.php

    public function getSummary(?string $from = null, ?string $to = null): array
    {
        if (! $from && ! $to) {
            $from = Carbon::now()->startOfMonth()->toDateString();
            $to = Carbon::now()->endOfMonth()->toDateString();
        }

        $query = Sale::query();

        if ($from) {
            $query->where('sale_date', '>=', $from);
        }
        if ($to) {
            $query->where('sale_date', '<=', $to);
        }

        // ✅ Compute total_sales_usd from asp_per_kg * quantity_kg
        $totals = $query->select([
            DB::raw('COALESCE(SUM(quantity_kg), 0) as total_quantity_kg'),
            DB::raw('COALESCE(SUM(asp_per_kg * quantity_kg), 0) as total_sales_usd'),
            DB::raw('COALESCE(SUM(CASE WHEN market = "Export" THEN 1 ELSE 0 END), 0) as export_count'),
            DB::raw('COALESCE(SUM(CASE WHEN market = "Local" THEN 1 ELSE 0 END), 0) as local_count'),
        ])->first();

        // ✅ Same fix for detailed summary
        $detailedSummary = Sale::with('product')
            ->select([
                'product_id',
                'market',
                DB::raw('SUM(quantity_kg) as total_quantity_kg'),
                DB::raw('SUM(asp_per_kg * quantity_kg) as total_sales_usd'),
                DB::raw('ROUND(SUM(asp_per_kg * quantity_kg) / NULLIF(SUM(quantity_kg), 0), 4) as avg_asp_per_kg'),
            ])
            ->when($from, fn ($q) => $q->where('sale_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('sale_date', '<=', $to))
            ->groupBy('product_id', 'market')
            ->orderBy('product_id')
            ->get();

        return [
            'total_sales_usd' => (float) $totals->total_sales_usd,
            'total_quantity_kg' => (float) $totals->total_quantity_kg,
            'export_count' => (int) $totals->export_count,
            'local_count' => (int) $totals->local_count,
            'detailed_summary' => $detailedSummary,
            'from' => $from,
            'to' => $to,
        ];
    }
}
