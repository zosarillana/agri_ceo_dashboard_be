<?php

namespace App\Services;

use App\Enum\RealtimeAction;
use App\Enum\RealtimeModule;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        private RealtimeService $realtime
    ) {}

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
                'sales' => $row['sales'],
                'asp_per_kg' => $row['asp_per_kg'] ?? null,
                'quantity_kg' => $row['quantity_kg'],
                'sale_date' => $date,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $rows);

        Sale::upsert(
            $data,
            ['product_id', 'sale_date'],
            ['market', 'sales', 'asp_per_kg', 'quantity_kg']
        );

        $saved = Sale::where('sale_date', $date)
            ->whereIn('product_id', array_column($rows, 'product_id'))
            ->get();

        $this->realtime->emit(
            RealtimeModule::SALE,
            RealtimeAction::BULK_CREATED,
            [
                'count' => $saved->count(),
                'ids' => $saved->pluck('id')->values(),
            ]
        );

        return $saved;
    }

    /**
     * Latest sale per product, optionally filtered to a date range.
     * Defaults to current month if no dates provided.
     */
    public function getLatest(?string $from = null, ?string $to = null): Collection
    {
        if (! $from && ! $to) {
            $from = Carbon::now()->startOfMonth()->toDateString();
            $to = Carbon::now()->endOfMonth()->toDateString();
        } else {
            if ($from && ! $to) {
                $to = Carbon::parse($from)->endOfMonth()->toDateString();
            }
            if (! $from && $to) {
                $from = Carbon::parse($to)->startOfMonth()->toDateString();
            }
        }

        \Log::info('getLatest called', ['from' => $from, 'to' => $to]);

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        $availableDates = Sale::select('sale_date')
            ->distinct()
            ->whereDate('sale_date', '>=', $fromDate->toDateString())
            ->whereDate('sale_date', '<=', $toDate->toDateString())
            ->pluck('sale_date')
            ->map(fn ($date) => $date->toDateString())
            ->toArray();

        \Log::info('Available dates in range', ['dates' => $availableDates]);

        if (empty($availableDates)) {
            \Log::warning('No data found in date range', ['from' => $from, 'to' => $to]);
            return collect();
        }

        return Sale::with('product')
            ->latestPerProduct(
                $fromDate->toDateString(),
                $toDate->toDateString()
            )
            ->orderBy('product_id')
            ->get();
    }

    /**
     * Summary totals for the matching sales.
     * Defaults to current month if no dates provided.
     *
     * UPDATED: asp_total_usd is now just the SUM of asp_total_usd (no division)
     */
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

        $totals = $query->select([
            DB::raw('COALESCE(SUM(quantity_kg), 0) as total_quantity_kg'),
            DB::raw('COALESCE(SUM(total_sales_usd), 0) as total_sales_usd'),
            DB::raw('COALESCE(SUM(sales), 0) as total_sales_raw'),
            // Just sum asp_total_usd directly (no division)
            DB::raw('ROUND(COALESCE(SUM(asp_total_usd), 0), 2) as asp_total_usd'),
            DB::raw('COALESCE(SUM(CASE WHEN market = "Export" THEN 1 ELSE 0 END), 0) as export_count'),
            DB::raw('COALESCE(SUM(CASE WHEN market = "Local" THEN 1 ELSE 0 END), 0) as local_count'),
        ])->first();

        $detailedSummary = Sale::with('product')
            ->select([
                'product_id',
                'market',
                DB::raw('SUM(quantity_kg) as total_quantity_kg'),
                DB::raw('SUM(total_sales_usd) as total_sales_usd'),
                DB::raw('SUM(sales) as total_sales_raw'),
                // Just sum asp_total_usd directly (no division)
                DB::raw('ROUND(SUM(asp_total_usd), 2) as asp_total_usd'),
            ])
            ->when($from, fn ($q) => $q->where('sale_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('sale_date', '<=', $to))
            ->groupBy('product_id', 'market')
            ->orderBy('product_id')
            ->get();

        return [
            'total_sales_usd' => (float) $totals->total_sales_usd,
            'total_sales_raw' => (float) $totals->total_sales_raw,
            'total_quantity_kg' => (float) $totals->total_quantity_kg,
            'asp_total_usd' => (float) $totals->asp_total_usd,
            'export_count' => (int) $totals->export_count,
            'local_count' => (int) $totals->local_count,
            'detailed_summary' => $detailedSummary,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Delete a sale entry by product_id and sale_date.
     * Returns true if a record was found and deleted, false if not found.
     */
    public function deleteBySaleDate(int $productId, string $saleDate): bool
    {
        $sale = Sale::where('product_id', $productId)
            ->whereDate('sale_date', Carbon::parse($saleDate)->toDateString())
            ->first();

        if (! $sale) {
            return false;
        }

        $id = $sale->id;
        $sale->delete();

        $this->realtime->emit(
            RealtimeModule::SALE,
            RealtimeAction::DELETED,
            ['id' => $id]
        );

        return true;
    }

    public function getAll(?string $from = null, ?string $to = null): Collection
    {
        if (! $from && ! $to) {
            $from = Carbon::now()->startOfMonth()->toDateString();
            $to = Carbon::now()->endOfMonth()->toDateString();
        }

        $query = Sale::with('product');

        if ($from) {
            $query->where('sale_date', '>=', $from);
        }
        if ($to) {
            $query->where('sale_date', '<=', $to);
        }

        return $query->orderBy('sale_date')->orderBy('product_id')->get();
    }

    /**
     * ALL sale rows in a date range (not collapsed to latest-per-product).
     * Defaults to current month if no dates provided.
     */
    public function getBetween(?string $from = null, ?string $to = null): Collection
    {
        if (! $from && ! $to) {
            $from = Carbon::now()->startOfMonth()->toDateString();
            $to = Carbon::now()->endOfMonth()->toDateString();
        }

        $query = Sale::with('product');

        if ($from) {
            $query->where('sale_date', '>=', $from);
        }
        if ($to) {
            $query->where('sale_date', '<=', $to);
        }

        return $query
            ->orderBy('sale_date')
            ->orderBy('product_id')
            ->get();
    }
}