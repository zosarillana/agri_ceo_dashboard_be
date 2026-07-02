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
     * If rows have 'id', update existing records; otherwise create new ones.
     */
    public function storeBulk(array $rows, ?string $saleDate = null): Collection
    {
        $date = $saleDate
            ? Carbon::parse($saleDate)->toDateString()
            : Carbon::today()->toDateString();

        $updatedIds = [];
        $createdIds = [];

        foreach ($rows as $row) {
            $data = [
                'product_id' => $row['product_id'],
                'market' => $row['market'],
                'sales' => $row['sales'],
                'asp_per_kg' => $row['asp_per_kg'] ?? null,
                'quantity_kg' => $row['quantity_kg'],
                'sale_date' => $date,
                'updated_at' => now(),
            ];

            // Check if this is an update (has id) or a new record
            if (isset($row['id']) && ! empty($row['id'])) {
                // Find and update existing record
                $sale = Sale::find($row['id']);
                if ($sale) {
                    $sale->update($data);
                    $updatedIds[] = $sale->id;

                    // Emit update event
                    $this->realtime->emit(
                        RealtimeModule::SALE,
                        RealtimeAction::UPDATED,
                        ['id' => $sale->id]
                    );

                    continue;
                }
            }

            // Create new record
            $data['created_at'] = now();
            $sale = Sale::create($data);
            $createdIds[] = $sale->id;

            // Emit create event
            $this->realtime->emit(
                RealtimeModule::SALE,
                RealtimeAction::CREATED,
                ['id' => $sale->id]
            );
        }

        // Get all affected records
        $allIds = array_merge($updatedIds, $createdIds);
        $saved = Sale::whereIn('id', $allIds)->with('product')->get();

        $this->realtime->emit(
            RealtimeModule::SALE,
            RealtimeAction::BULK_CREATED,
            [
                'created' => $createdIds,
                'updated' => $updatedIds,
                'total' => count($allIds),
            ]
        );

        return $saved;
    }

    /**
     * Alternative: Update or create using unique constraint
     * This uses the database's unique constraint on (product_id, sale_date)
     */
    public function storeBulkWithUpsert(array $rows, ?string $saleDate = null): Collection
    {
        $date = $saleDate
            ? Carbon::parse($saleDate)->toDateString()
            : Carbon::today()->toDateString();

        $updatedIds = [];
        $createdIds = [];

        foreach ($rows as $row) {
            $data = [
                'market' => $row['market'],
                'sales' => $row['sales'],
                'asp_per_kg' => $row['asp_per_kg'] ?? null,
                'quantity_kg' => $row['quantity_kg'],
                'updated_at' => now(),
            ];

            // Find existing record by product_id and sale_date
            $sale = Sale::where('product_id', $row['product_id'])
                ->whereDate('sale_date', $date)
                ->first();

            if ($sale) {
                // Update existing
                $sale->update($data);
                $updatedIds[] = $sale->id;
            } else {
                // Create new
                $data['product_id'] = $row['product_id'];
                $data['sale_date'] = $date;
                $data['created_at'] = now();
                $sale = Sale::create($data);
                $createdIds[] = $sale->id;
            }
        }

        // Get all affected records
        $allIds = array_merge($updatedIds, $createdIds);
        $saved = Sale::whereIn('id', $allIds)->with('product')->get();

        $this->realtime->emit(
            RealtimeModule::SALE,
            RealtimeAction::BULK_CREATED,
            [
                'created' => $createdIds,
                'updated' => $updatedIds,
                'total' => count($allIds),
            ]
        );

        return $saved;
    }

    /**
     * Update a single sale record
     */
    public function updateSale(int $id, array $data): ?Sale
    {
        $sale = Sale::find($id);

        if (! $sale) {
            return null;
        }

        $sale->update([
            'product_id' => $data['product_id'],
            'market' => $data['market'],
            'sales' => $data['sales'],
            'asp_per_kg' => $data['asp_per_kg'] ?? null,
            'quantity_kg' => $data['quantity_kg'],
            'sale_date' => $data['sale_date'] ?? $sale->sale_date,
            'updated_at' => now(),
        ]);

        $this->realtime->emit(
            RealtimeModule::SALE,
            RealtimeAction::UPDATED,
            ['id' => $sale->id]
        );

        return $sale->fresh();
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
     */
    /**
     * Summary totals for the matching sales.
     * Defaults to current month if no dates provided.
     */
    /**
     * Summary totals for the matching sales.
     * Defaults to current month if no dates provided.
     */
    /**
     * Summary totals for the matching sales.
     * Defaults to current month if no dates provided.
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
            // Calculate overall ASP as total_sales_usd / total_quantity_kg
            DB::raw('ROUND(COALESCE(SUM(total_sales_usd) / NULLIF(SUM(quantity_kg), 0), 0), 2) as asp_total_usd'),
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
                DB::raw('ROUND(SUM(total_sales_usd) / NULLIF(SUM(quantity_kg), 0), 2) as asp_total_usd'),
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
            'asp_total_usd' => (float) $totals->asp_total_usd, // Now will be 370/222.3 = 1.66
            'export_count' => (int) $totals->export_count,
            'local_count' => (int) $totals->local_count,
            'detailed_summary' => $detailedSummary,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Delete a sale entry by ID
     */
    public function deleteById(int $id): bool
    {
        $sale = Sale::find($id);

        if (! $sale) {
            return false;
        }

        $sale->delete();

        $this->realtime->emit(
            RealtimeModule::SALE,
            RealtimeAction::DELETED,
            ['id' => $id]
        );

        return true;
    }

    /**
     * Delete a sale entry by product_id and sale_date.
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
