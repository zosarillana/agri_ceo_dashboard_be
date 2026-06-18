<?php

namespace App\Services;

use App\Models\Account;
use App\Models\EnergyRecord;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceUnit;
use App\Models\ProductionEntry;
use App\Models\QcRecord;
use App\Models\Sale;
use App\Models\Trade;
use App\Models\WorkforceRecord;
use Carbon\Carbon;

class DashboardService
{
    protected EnergyService $energyService;

    protected QcService $qcService;

    protected ProcurementService $procurementService;

    protected TradeService $tradeService;

    protected AccountService $accountService;

    public function __construct(
        EnergyService $energyService,
        QcService $qcService,
        ProcurementService $procurementService,
        TradeService $tradeService,
        AccountService $accountService
    ) {
        $this->energyService = $energyService;
        $this->qcService = $qcService;
        $this->procurementService = $procurementService;
        $this->tradeService = $tradeService;
        $this->accountService = $accountService;
    }

    public function getDashboardStats(?string $date = null): array
    {
        return [
            'production' => $this->getProductionStats($date),
            'maintenance' => $this->getMaintenanceStats(),
            'sales' => $this->getSalesStats(),
            'energy' => $this->getEnergyStats($date),
            'workforce' => $this->getWorkforceStats($date),
            'qc' => $this->getQcStats($date),
            'procurement' => $this->getProcurementStats($date),
            'trading' => $this->getTradeStats($date),
            'accounts' => $this->getAccountStats($date),
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

        $lastUnitUpdate = $baseQuery()->latest('updated_at')->value('updated_at');
        $lastLogUpdate = MaintenanceLog::latest('checked_at')->value('checked_at');

        $lastUpdated = collect([$lastUnitUpdate, $lastLogUpdate])
            ->filter()
            ->max();

        return [
            'total_units' => $total,
            'checked_today' => $checkedToday,
            'unchecked_today' => $total - $checkedToday,
            'completion' => $total > 0 ? round(($checkedToday / $total) * 100) : 0,
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

        $momChange = $lastUSD > 0
            ? round((($thisUSD - $lastUSD) / $lastUSD) * 100, 1)
            : null;

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
        $billingMonth = $date ? Carbon::parse($date) : now();
        $currentMonthStart = $billingMonth->copy()->startOfMonth()->toDateString();
        $previousMonthStart = $billingMonth->copy()->subMonth()->startOfMonth()->toDateString();
        $previousMonthEnd = $billingMonth->copy()->subMonth()->endOfMonth()->toDateString();

        $currentMonthRecords = EnergyRecord::whereDate('billing_month', $currentMonthStart)->get();
        $previousMonthRecords = EnergyRecord::whereDate('billing_month', '>=', $previousMonthStart)
            ->whereDate('billing_month', '<=', $previousMonthEnd)
            ->get();

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

        $previousMonthTotal = [
            'total_billed' => (float) $previousMonthRecords->sum('billed_amount'),
            'total_kw' => (float) $previousMonthRecords->sum('kw'),
            'total_demand' => (float) $previousMonthRecords->sum('demand'),
            'account2_billed' => (float) $previousMonthRecords->where('account', 'account2')->sum('billed_amount'),
            'account3_billed' => (float) $previousMonthRecords->where('account', 'account3')->sum('billed_amount'),
            'has_data' => $previousMonthRecords->isNotEmpty(),
            'month' => $billingMonth->copy()->subMonth()->format('Y-m'),
        ];

        $momChange = null;
        if ($previousMonthTotal['total_billed'] > 0) {
            $momChange = round(
                (($currentMonthTotal['total_billed'] - $previousMonthTotal['total_billed']) / $previousMonthTotal['total_billed']) * 100,
                1
            );
        }

        $recordsByAccount = [
            'account2' => $currentMonthRecords->where('account', 'account2')->values(),
            'account3' => $currentMonthRecords->where('account', 'account3')->values(),
        ];

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

        $allRecords = EnergyRecord::all();
        $ytdSummary = [
            'total_billed_amount' => (float) $allRecords->sum('billed_amount'),
            'total_kw' => (float) $allRecords->sum('kw'),
            'total_demand' => (float) $allRecords->sum('demand'),
            'account2_total' => (float) $allRecords->where('account', 'account2')->sum('billed_amount'),
            'account3_total' => (float) $allRecords->where('account', 'account3')->sum('billed_amount'),
        ];

        return [
            'current_month' => $currentMonthTotal,
            'previous_month' => $previousMonthTotal,
            'mom_change_pct' => $momChange,
            'records' => $recordsByAccount,
            'ytd_summary' => $ytdSummary,
            'monthly_trends' => $monthlyTrends,
            'total_accounts' => EnergyRecord::distinct('account')->count('account'),
            'total_months' => EnergyRecord::distinct('billing_month')->count('billing_month'),
            'last_updated_at' => EnergyRecord::latest('updated_at')
                ->value('updated_at')?->toISOString(),
        ];
    }

    public function getWorkforceStats(?string $date = null): array
    {
        $date = $date ?? now()->toDateString();

        // Only fetch records for the exact requested date (no fallback to prior dates).
        // If no attendance has been recorded yet for that date, return a zeroed response.
        $records = WorkforceRecord::query()
            ->where('record_date', $date)
            ->orderBy('section')
            ->orderBy('department_key')
            ->get();

        // No attendance recorded for this date yet
        if ($records->isEmpty()) {
            return [
                'total_present' => 0,
                'total_headcount' => 0,
                'total_incidents' => 0,
                'attendance_rate' => null,
                'department_count' => 0,
                'by_section' => [],
                'lowest_dept' => null,
                'departments' => [],
                'has_data' => false,
                'last_updated_at' => WorkforceRecord::latest('updated_at')
                    ->value('updated_at')?->toISOString(),
            ];
        }

        $totalPresent = (int) $records->sum('present');
        $totalHeadcount = (int) $records->sum('headcount');
        $totalIncidents = (int) $records->sum('incidents');
        $attendanceRate = $totalHeadcount > 0
            ? round(($totalPresent / $totalHeadcount) * 100, 1)
            : null;

        $bySection = $records
            ->groupBy('section')
            ->map(fn ($rows) => [
                'present' => (int) $rows->sum('present'),
                'headcount' => (int) $rows->sum('headcount'),
                'incidents' => (int) $rows->sum('incidents'),
                'rate' => $rows->sum('headcount') > 0
                    ? round(($rows->sum('present') / $rows->sum('headcount')) * 100, 1)
                    : null,
            ]);

        $lowestDept = $records
            ->filter(fn ($r) => $r->headcount > 0)
            ->sortBy(fn ($r) => $r->present / $r->headcount)
            ->first();

        return [
            'total_present' => $totalPresent,
            'total_headcount' => $totalHeadcount,
            'total_incidents' => $totalIncidents,
            'attendance_rate' => $attendanceRate,
            'department_count' => $records->count(),
            'by_section' => $bySection,
            'lowest_dept' => $lowestDept ? [
                'label' => $lowestDept->department_label,
                'rate' => $lowestDept->attendance_rate,
            ] : null,
            'departments' => $records->map(fn ($r) => [
                'key' => $r->department_key,
                'label' => $r->department_label,
                'section' => $r->section,
                'present' => $r->present,
                'headcount' => $r->headcount,
                'incidents' => $r->incidents,
                'rate' => $r->attendance_rate,
            ])->values(),
            'has_data' => true,
            'last_updated_at' => WorkforceRecord::latest('updated_at')
                ->value('updated_at')?->toISOString(),
        ];
    }

    /**
     * Get Quality Control statistics for the dashboard.
     * Now properly filters by specific date for daily data.
     */
    public function getQcStats(?string $date = null): array
    {
        $targetDate = $date ? Carbon::parse($date) : now();
        $targetDateString = $targetDate->toDateString();

        // Get current month range (based on the target date's month)
        $currentMonthStart = $targetDate->copy()->startOfMonth()->toDateString();
        $currentMonthEnd = $targetDate->copy()->endOfMonth()->toDateString();

        // Get previous month range for comparison
        $previousMonthStart = $targetDate->copy()->subMonth()->startOfMonth()->toDateString();
        $previousMonthEnd = $targetDate->copy()->subMonth()->endOfMonth()->toDateString();

        // Get current month summary
        $currentMonthSummary = $this->qcService->getSummary($currentMonthStart, $currentMonthEnd);

        // Get previous month summary for comparison
        $previousMonthSummary = $this->qcService->getSummary($previousMonthStart, $previousMonthEnd);

        // Calculate month-over-month change for pass rate
        $momPassRateChange = null;
        if ($previousMonthSummary['pass_rate'] > 0) {
            $momPassRateChange = round(
                $currentMonthSummary['pass_rate'] - $previousMonthSummary['pass_rate'],
                2
            );
        }

        // Get weekly breakdown for current month
        $weeklyBreakdown = $this->getWeeklyQcBreakdown($currentMonthStart, $currentMonthEnd);

        // Get latest QC records for current month (top 5 by tested samples)
        $latestRecords = $this->qcService->getLatest($currentMonthStart, $currentMonthEnd)
            ->sortByDesc('tested')
            ->take(5)
            ->values()
            ->map(fn ($record) => [
                'product_name' => $record->product?->name ?? 'Unknown Product',
                'tested' => $record->tested,
                'passed' => $record->passed,
                'failed' => $record->tested - $record->passed,
                'pass_rate' => $record->tested > 0
                    ? round(($record->passed / $record->tested) * 100, 2)
                    : 0,
                'qc_date' => $record->qc_date,
            ]);

        // Get daily trend for current month
        $dailyTrend = QcRecord::whereBetween('qc_date', [$currentMonthStart, $currentMonthEnd])
            ->selectRaw('qc_date, SUM(tested) as total_tested, SUM(passed) as total_passed')
            ->groupBy('qc_date')
            ->orderBy('qc_date')
            ->get()
            ->map(fn ($day) => [
                'date' => $day->qc_date,
                'tested' => (int) $day->total_tested,
                'passed' => (int) $day->total_passed,
                'failed' => (int) ($day->total_tested - $day->total_passed),
                'pass_rate' => $day->total_tested > 0
                    ? round(($day->total_passed / $day->total_tested) * 100, 2)
                    : 0,
            ]);

        // FIXED: Get data for the specific requested date (not hardcoded to now())
        $requestedDayData = $dailyTrend->firstWhere('date', $targetDateString);

        // If no data for the requested date, create a zeroed entry
        if (! $requestedDayData) {
            $requestedDayData = [
                'date' => $targetDateString,
                'tested' => 0,
                'passed' => 0,
                'failed' => 0,
                'pass_rate' => 0,
            ];
        }

        // Get product performance summary
        $productPerformance = $this->qcService->getLatest($currentMonthStart, $currentMonthEnd)
            ->map(fn ($record) => [
                'product_name' => $record->product?->name ?? 'Unknown Product',
                'tested' => $record->tested,
                'passed' => $record->passed,
                'failed' => $record->tested - $record->passed,
                'pass_rate' => $record->tested > 0
                    ? round(($record->passed / $record->tested) * 100, 2)
                    : 0,
            ])
            ->sortByDesc('tested')
            ->values();

        // Get monthly breakdown for historical navigation
        $monthlyBreakdown = QcRecord::selectRaw('
                DATE_FORMAT(qc_date, "%Y-%m") as month,
                SUM(tested) as samples_tested,
                SUM(passed) as samples_passed,
                COUNT(DISTINCT product_id) as products_tested
            ')
            ->where('qc_date', '>=', $targetDate->copy()->subMonths(5)->startOfMonth())
            ->groupByRaw('DATE_FORMAT(qc_date, "%Y-%m")')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'samples_tested' => (int) $row->samples_tested,
                'samples_passed' => (int) $row->samples_passed,
                'samples_failed' => (int) ($row->samples_tested - $row->samples_passed),
                'pass_rate' => $row->samples_tested > 0
                    ? round(($row->samples_passed / $row->samples_tested) * 100, 2)
                    : 0,
                'rejection_rate' => $row->samples_tested > 0
                    ? round((($row->samples_tested - $row->samples_passed) / $row->samples_tested) * 100, 2)
                    : 0,
                'products_tested' => (int) $row->products_tested,
            ]);

        return [
            'today' => $requestedDayData, // FIXED: Now returns data for the requested date
            'current_month' => [
                'samples_tested' => $currentMonthSummary['samples_tested'],
                'samples_passed' => $currentMonthSummary['samples_passed'],
                'samples_failed' => $currentMonthSummary['samples_failed'],
                'pass_rate' => $currentMonthSummary['pass_rate'],
                'rejection_rate' => $currentMonthSummary['rejection_rate'],
                'products_tested' => $currentMonthSummary['products_tested'],
                'month' => $targetDate->format('Y-m'),
            ],
            'previous_month' => [
                'samples_tested' => $previousMonthSummary['samples_tested'],
                'samples_passed' => $previousMonthSummary['samples_passed'],
                'samples_failed' => $previousMonthSummary['samples_failed'],
                'pass_rate' => $previousMonthSummary['pass_rate'],
                'rejection_rate' => $previousMonthSummary['rejection_rate'],
                'products_tested' => $previousMonthSummary['products_tested'],
                'month' => $targetDate->copy()->subMonth()->format('Y-m'),
            ],
            'mom_pass_rate_change' => $momPassRateChange,
            'weekly_breakdown' => $weeklyBreakdown,
            'daily_trend' => $dailyTrend,
            'monthly_breakdown' => $monthlyBreakdown, // Added for historical navigation
            'top_products' => $latestRecords,
            'product_performance' => $productPerformance,
            'has_data' => $currentMonthSummary['samples_tested'] > 0,
            'last_updated_at' => QcRecord::latest('updated_at')
                ->value('updated_at')?->toISOString(),
        ];
    }

    /**
     * Get weekly breakdown of QC data for a date range.
     */
    protected function getWeeklyQcBreakdown(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $weeks = [];
        $current = $start->copy();

        while ($current <= $end) {
            $weekStart = $current->copy()->startOfWeek();
            $weekEnd = $current->copy()->endOfWeek();

            // Adjust week end to not exceed the month end
            if ($weekEnd > $end) {
                $weekEnd = $end->copy();
            }

            $weekData = QcRecord::whereBetween('qc_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->selectRaw('SUM(tested) as total_tested, SUM(passed) as total_passed')
                ->first();

            $tested = (int) ($weekData->total_tested ?? 0);
            $passed = (int) ($weekData->total_passed ?? 0);

            $weeks[] = [
                'week' => 'Week '.$current->weekOfMonth,
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'tested' => $tested,
                'passed' => $passed,
                'failed' => $tested - $passed,
                'pass_rate' => $tested > 0 ? round(($passed / $tested) * 100, 2) : 0,
            ];

            $current->addWeek();
        }

        return $weeks;
    }

    /**
     * Get Procurement statistics for the dashboard.
     */
    public function getProcurementStats(?string $date = null): array
    {
        $targetDate = $date ? Carbon::parse($date) : now();
        $from = $targetDate->copy()->startOfMonth()->toDateString();
        $to = $targetDate->copy()->endOfMonth()->toDateString();

        $summary = $this->procurementService->getSummary($from, $to);

        return array_merge($summary, [
            'has_data' => $summary['total_items'] > 0,
            'month' => $targetDate->format('Y-m'),
        ]);
    }

    /**
     * Get Trade statistics for the dashboard.
     */
    public function getTradeStats(?string $date = null): array
    {
        $targetDate = $date ? Carbon::parse($date) : now();
        $from = $targetDate->copy()->startOfMonth()->toDateString();
        $to = $targetDate->copy()->endOfMonth()->toDateString();

        $summary = $this->tradeService->getSummary($from, $to);

        return array_merge($summary, [
            'has_data' => $summary['total_orders'] > 0,
            'month' => $targetDate->format('Y-m'),
            'last_updated_at' => Trade::latest('updated_at')
                ->value('updated_at')?->toISOString(),
        ]);
    }

    /**
     * Get Account/Financial statistics for the dashboard.
     */
    public function getAccountStats(?string $date = null): array
    {
        $targetDate = $date ? Carbon::parse($date) : now();
        $from = $targetDate->copy()->startOfMonth()->toDateString();
        $to = $targetDate->copy()->endOfMonth()->toDateString();

        $summary = $this->accountService->getSummary($from, $to);
        $hasData = ($summary['total_receivable'] + $summary['total_payable'] + $summary['total_capex'] + $summary['total_opex']) > 0;

        return array_merge($summary, [
            'has_data' => $hasData,
            'month' => $targetDate->format('Y-m'),
            'last_updated_at' => Account::latest('updated_at')  // add this
                ->value('updated_at')?->toISOString(),
        ]);
    }
}
