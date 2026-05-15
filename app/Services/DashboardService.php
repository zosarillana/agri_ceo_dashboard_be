<?php

namespace App\Services;

use App\Models\ProductionEntry;
use Carbon\Carbon;

class DashboardService
{
    // public function getProductionStats(): array
    // {
    //     $today = now()->toDateString();

    //     $todayProductionTotal = ProductionEntry::whereDate('production_date', $today)
    //         ->sum('actual_output');

    //     return [
    //         // REAL KPI (what you actually want)
    //         'today_production_output' => (float) $todayProductionTotal,

    //         // optional extra metrics (keep or remove later)
    //         'total_production_entries' => ProductionEntry::count(),
    //         'this_month_production_entries' => ProductionEntry::whereMonth('created_at', now()->month)->count(),
    //         'last_updated_at' => ProductionEntry::latest('updated_at')
    //             ->value('updated_at')?->toISOString(),
    //     ];
    // }
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
}
