<?php

namespace App\Services;

use App\Models\MaintenanceUnit;
use App\Models\ProductionEntry;
use App\Models\Sale;
use App\Models\MaintenanceLog;
use App\Models\EnergyRecord;
use Carbon\Carbon;

class DashboardService
{
    protected EnergyService $energyService;

    public function __construct(EnergyService $energyService)
    {
        $this->energyService = $energyService;
    }

    public function getDashboardStats(?string $date = null): array
    {
        return [
            'production' => $this->getProductionStats($date),
            'maintenance' => $this->getMaintenanceStats(),
            'sales' => $this->getSalesStats(),
            'energy' => $this->getEnergyStats($date),
        ];
    }

    public function getProductionStats(?string $date = null): array
    {
        $date = $date ?? now()->toDateString();
        $yesterday = Carbon::parse($date)->subDay()->toDateString();

        $dateTotal = ProductionEntry::whereDate('production_date', $date)
            ->sum('actual_output');

        $yesterdayTotal = ProductionEntry::whereDate('production_date', $yesterday)
            ->sum('actual_output');

        return [
            'today_production_output' => (float) $dateTotal,
            'yesterday_production_output' => (float) $yesterdayTotal,
            'total_production_entries' => ProductionEntry::count(),
            'this_month_production_entries' => ProductionEntry::whereMonth('created_at', now()->month)->count(),
            'last_updated_at' => ProductionEntry::latest('updated_at')
                ->value('updated_at')?->toISOString(),
        ];
    }

    public function getMaintenanceStats(): array
    {
        // Only top-level units (no subunits) — matches what the UI shows
        $baseQuery = fn () => MaintenanceUnit::active()->whereNull('parent_id');

        $statusCounts = $baseQuery()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $statuses = ['operational', 'maintenance', 'standby', 'down'];

        $statusBreakdown = collect($statuses)
            ->mapWithKeys(fn ($s) => [$s => (int) ($statusCounts[$s] ?? 0)])
            ->all();

        $total = $baseQuery()->count();
        $checkedToday = $baseQuery()
            ->whereHas('logs', fn ($q) => $q->whereDate('checked_at', today()))
            ->count();

        // Use the most recent unit update or log check as the last_updated_at
        $lastUnitUpdate = $baseQuery()->latest('updated_at')->value('updated_at');
        $lastLogUpdate = MaintenanceLog::latest('checked_at')->value('checked_at');

        $lastUpdated = collect([$lastUnitUpdate, $lastLogUpdate])
            ->filter()
            ->max();

        return [
            'total_units' => $total,
            'checked_today' => $checkedToday,
            'unchecked_today' => $total - $checkedToday,
            'completion' => $total > 0
                ? round(($checkedToday / $total) * 100)
                : 0,
            'status_breakdown' => $statusBreakdown,
            'last_updated_at' => $lastUpdated
                ? Carbon::parse($lastUpdated)->toISOString()
                : null,
        ];
    }

    public function getSalesStats(): array
    {
        $now = now();
        $thisMonthStart = $now->copy()->startOfMonth()->toDateString();
        $thisMonthEnd = $now->copy()->endOfMonth()->toDateString();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth()->toDateString();

        // This month: sum all sales within the month (not just latest-per-product)
        $thisMonth = Sale::whereDate('sale_date', '>=', $thisMonthStart)
            ->whereDate('sale_date', '<=', $thisMonthEnd)
            ->selectRaw('
                SUM(total_sales_usd) as total_usd,
                SUM(quantity_kg)     as total_kg,
                COUNT(*)             as entry_count,
                COUNT(CASE WHEN market = "Export" THEN 1 END) as export_count,
                COUNT(CASE WHEN market = "Local"  THEN 1 END) as local_count
            ')
            ->first();

        // Last month: same aggregation for comparison
        $lastMonth = Sale::whereDate('sale_date', '>=', $lastMonthStart)
            ->whereDate('sale_date', '<=', $lastMonthEnd)
            ->selectRaw('
                SUM(total_sales_usd) as total_usd,
                SUM(quantity_kg)     as total_kg,
                COUNT(*)             as entry_count
            ')
            ->first();

        $thisUSD = (float) ($thisMonth->total_usd ?? 0);
        $lastUSD = (float) ($lastMonth->total_usd ?? 0);

        // Month-over-month % change, null if no last month data to compare
        $momChange = $lastUSD > 0
            ? round((($thisUSD - $lastUSD) / $lastUSD) * 100, 1)
            : null;

        // Per-month breakdown for the last 6 months (for a chart)
        $monthlyBreakdown = Sale::selectRaw('
                DATE_FORMAT(sale_date, "%Y-%m") as month,
                SUM(total_sales_usd)            as total_usd,
                SUM(quantity_kg)                as total_kg,
                COUNT(*)                        as entry_count
            ')
            ->where('sale_date', '>=', $now->copy()->subMonths(5)->startOfMonth())
            ->groupByRaw('DATE_FORMAT(sale_date, "%Y-%m")')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'total_usd' => (float) $row->total_usd,
                'total_kg' => (float) $row->total_kg,
                'entry_count' => (int) $row->entry_count,
            ]);

        return [
            'this_month' => [
                'total_usd' => $thisUSD,
                'total_kg' => (float) ($thisMonth->total_kg ?? 0),
                'entry_count' => (int) ($thisMonth->entry_count ?? 0),
                'export_count' => (int) ($thisMonth->export_count ?? 0),
                'local_count' => (int) ($thisMonth->local_count ?? 0),
            ],
            'last_month' => [
                'total_usd' => $lastUSD,
                'total_kg' => (float) ($lastMonth->total_kg ?? 0),
                'entry_count' => (int) ($lastMonth->entry_count ?? 0),
            ],
            'mom_change_pct' => $momChange,
            'monthly_breakdown' => $monthlyBreakdown,
            'last_updated_at' => Sale::latest('updated_at')
                ->value('updated_at')?->toISOString(),
        ];
    }

    public function getEnergyStats(?string $date = null): array
    {
        // Parse the date to get the billing month (same as production uses)
        $billingMonth = $date ? Carbon::parse($date) : now();
        $currentMonthStart = $billingMonth->copy()->startOfMonth()->toDateString();
        $previousMonthStart = $billingMonth->copy()->subMonth()->startOfMonth()->toDateString();
        $previousMonthEnd = $billingMonth->copy()->subMonth()->endOfMonth()->toDateString();

        // Get current month energy records
        $currentMonthRecords = EnergyRecord::whereDate('billing_month', $currentMonthStart)
            ->get();

        // Get previous month energy records
        $previousMonthRecords = EnergyRecord::whereDate('billing_month', '>=', $previousMonthStart)
            ->whereDate('billing_month', '<=', $previousMonthEnd)
            ->get();

        // Calculate current month totals
        $currentMonthTotal = [
            'total_billed' => (float) $currentMonthRecords->sum('billed_amount'),
            'total_kw' => (float) $currentMonthRecords->sum('kw'),
            'total_demand' => (float) $currentMonthRecords->sum('demand'),
            'account2_billed' => (float) $currentMonthRecords->where('account', 'account2')->sum('billed_amount'),
            'account3_billed' => (float) $currentMonthRecords->where('account', 'account3')->sum('billed_amount'),
            'account2_kw' => (float) $currentMonthRecords->where('account', 'account2')->sum('kw'),
            'account3_kw' => (float) $currentMonthRecords->where('account', 'account3')->sum('kw'),
            'has_data' => $currentMonthRecords->isNotEmpty(),
            'month' => $billingMonth->format('Y-m'),
        ];

        // Calculate previous month totals
        $previousMonthTotal = [
            'total_billed' => (float) $previousMonthRecords->sum('billed_amount'),
            'total_kw' => (float) $previousMonthRecords->sum('kw'),
            'total_demand' => (float) $previousMonthRecords->sum('demand'),
            'account2_billed' => (float) $previousMonthRecords->where('account', 'account2')->sum('billed_amount'),
            'account3_billed' => (float) $previousMonthRecords->where('account', 'account3')->sum('billed_amount'),
            'has_data' => $previousMonthRecords->isNotEmpty(),
            'month' => $billingMonth->copy()->subMonth()->format('Y-m'),
        ];

        // Calculate month-over-month change
        $momChange = null;
        if ($previousMonthTotal['total_billed'] > 0) {
            $momChange = round(
                (($currentMonthTotal['total_billed'] - $previousMonthTotal['total_billed']) / $previousMonthTotal['total_billed']) * 100,
                1
            );
        }

        // Get records grouped by account for the current month
        $recordsByAccount = [
            'account2' => $currentMonthRecords->where('account', 'account2')->values(),
            'account3' => $currentMonthRecords->where('account', 'account3')->values(),
        ];

        // Get monthly trends for the last 6 months (for chart)
        $monthlyTrends = EnergyRecord::selectRaw('
                DATE_FORMAT(billing_month, "%Y-%m") as month,
                SUM(billed_amount) as total_billed,
                SUM(kw) as total_kw,
                SUM(demand) as total_demand
            ')
            ->where('billing_month', '>=', now()->subMonths(5)->startOfMonth())
            ->groupByRaw('DATE_FORMAT(billing_month, "%Y-%m")')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'total_billed' => (float) $row->total_billed,
                'total_kw' => (float) $row->total_kw,
                'total_demand' => (float) $row->total_demand,
            ]);

        // Get YTD totals (all time summary)
        $allRecords = EnergyRecord::all();
        $ytdSummary = [
            'total_billed_amount' => (float) $allRecords->sum('billed_amount'),
            'total_kw' => (float) $allRecords->sum('kw'),
            'total_demand' => (float) $allRecords->sum('demand'),
            'account2_total' => (float) $allRecords->where('account', 'account2')->sum('billed_amount'),
            'account3_total' => (float) $allRecords->where('account', 'account3')->sum('billed_amount'),
        ];

        // Get latest update timestamp
        $lastUpdated = EnergyRecord::latest('updated_at')
            ->value('updated_at')?->toISOString();

        return [
            // Current month data (like sales.this_month)
            'current_month' => $currentMonthTotal,
            
            // Previous month data (like sales.last_month)
            'previous_month' => $previousMonthTotal,
            
            // Month-over-month change percentage
            'mom_change_pct' => $momChange,
            
            // Records grouped by account for the current month
            'records' => $recordsByAccount,
            
            // YTD Summary (all time totals)
            'ytd_summary' => $ytdSummary,
            
            // Monthly trends for chart
            'monthly_trends' => $monthlyTrends,
            
            // Metadata
            'total_accounts' => EnergyRecord::distinct('account')->count('account'),
            'total_months' => EnergyRecord::distinct('billing_month')->count('billing_month'),
            'last_updated_at' => $lastUpdated,
        ];
    }
}