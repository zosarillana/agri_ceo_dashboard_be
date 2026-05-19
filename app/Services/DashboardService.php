<?php

namespace App\Services;

use App\Models\ProductionEntry;
use App\Services\MaintenanceLogService;
use Carbon\Carbon;

class DashboardService
{
    public function getDashboardStats(?string $date = null): array
    {
        return [
            'production' => $this->getProductionStats($date),
            'maintenance' => $this->getMaintenanceStats(),
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
        /** @var MaintenanceLogService $service */
        $service = app(MaintenanceLogService::class);

        $summary = $service->getDailyCompletionSummary();

        $totalUnits = $summary->sum('total');
        $checked = $summary->sum('checked');
        $unchecked = $summary->sum('unchecked');

        return [
            'total_units' => $totalUnits,
            'checked_today' => $checked,
            'unchecked_today' => $unchecked,
            'completion' => $totalUnits > 0
                ? round(($checked / $totalUnits) * 100)
                : 0,
        ];
    }
}