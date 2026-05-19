<?php

namespace App\Services;

use App\Models\MaintenanceLog;
use App\Models\MaintenanceUnit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class MaintenanceLogService
{
    /**
     * Submit a check for a unit.
     *
     * This does two things atomically:
     *   1. Creates a new log entry (permanent history record)
     *   2. Updates the parent unit's status snapshot (what the dashboard shows)
     *
     * @param  array  $data  { status, notes?, checked_at?, next_scheduled_at?, duration_minutes? }
     */
    public function submitCheck(int $unitId, array $data): MaintenanceLog
    {
        $unit = MaintenanceUnit::findOrFail($unitId);

        $checkedAt = $data['checked_at'] ?? now();

        // 1. Write the log entry
        $log = MaintenanceLog::create([
            'maintenance_unit_id' => $unit->id,
            'checked_by' => Auth::id(),
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'checked_at' => $checkedAt,
            'next_scheduled_at' => $data['next_scheduled_at'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
        ]);

        // 2. Sync the unit's current snapshot so the dashboard stays up to date
        $unit->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $unit->notes,
            'last_checked_at' => $checkedAt,
            'next_scheduled_at' => $data['next_scheduled_at'] ?? $unit->next_scheduled_at,
        ]);

        return $log->load('checker', 'unit');
    }

    /**
     * Full log history for a single unit, paginated.
     * Includes who checked it and what they found.
     */
    public function getUnitHistory(int $unitId, int $perPage = 20): LengthAwarePaginator
    {
        return MaintenanceLog::with('checker')
            ->where('maintenance_unit_id', $unitId)
            ->orderByDesc('checked_at')
            ->paginate($perPage);
    }

    /**
     * All checks submitted today across all units, grouped by plant.
     * Used for the daily monitoring overview.
     */
    public function getTodaysChecks(): Collection
    {
        return MaintenanceLog::with(['unit.plant', 'checker'])
            ->today()
            ->orderByDesc('checked_at')
            ->get()
            ->groupBy(fn ($log) => $log->unit->plant->name)
            ->map(fn ($logs, $plantName) => [
                'plant' => $plantName,
                'checks' => $logs->map(fn ($log) => $this->formatLog($log))->values(),
            ])
            ->values();
    }

    /**
     * Units that have NOT been checked today.
     * Useful for sending reminders or flagging on the dashboard.
     */
    public function getUncheckedToday(): Collection
    {
        return MaintenanceUnit::active()
            ->topLevel()
            ->uncheckedToday()
            ->with('plant')
            ->get()
            ->map(fn ($unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'plant' => $unit->plant->name,
                'last_checked_at' => $unit->last_checked_at?->toISOString(),
            ]);
    }

    /**
     * Checks submitted by a specific user, paginated.
     */
    public function getUserHistory(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return MaintenanceLog::with(['unit.plant'])
            ->byUser($userId)
            ->orderByDesc('checked_at')
            ->paginate($perPage);
    }

    /**
     * Status history for a unit over a date range.
     * Used for trend graphs on the frontend.
     */
    public function getUnitStatusHistory(int $unitId, string $from, string $to): Collection
    {
        return MaintenanceLog::with('checker')
            ->where('maintenance_unit_id', $unitId)
            ->between($from, $to)
            ->orderBy('checked_at')
            ->get()
            ->map(fn ($log) => $this->formatLog($log));
    }

    /**
     * Daily check completion summary per plant.
     * Returns: how many units were checked today vs total.
     */
    public function getDailyCompletionSummary(): Collection
    {
        $allUnits = MaintenanceUnit::active()->topLevel()->with('plant')->get();

        $checkedTodayIds = MaintenanceLog::today()
            ->pluck('maintenance_unit_id')
            ->unique();

        return $allUnits
            ->groupBy(fn ($unit) => $unit->plant->name)
            ->map(fn ($units, $plantName) => [
                'plant' => $plantName,
                'total' => $units->count(),
                'checked' => $units->filter(fn ($u) => $checkedTodayIds->contains($u->id))->count(),
                'unchecked' => $units->filter(fn ($u) => ! $checkedTodayIds->contains($u->id))->count(),
                'completion' => $units->count() > 0
                    ? round(($units->filter(fn ($u) => $checkedTodayIds->contains($u->id))->count() / $units->count()) * 100)
                    : 0,
            ])
            ->values();
    }

    // ─── Private Formatters ───────────────────────────────────────────────────

    private function formatLog(MaintenanceLog $log): array
    {
        return [
            'id' => $log->id,
            'unit_id' => $log->maintenance_unit_id,
            'unit_name' => $log->unit->name,
            'status' => $log->status,
            'notes' => $log->notes,
            'checked_at' => $log->checked_at->toISOString(),
            'next_scheduled_at' => $log->next_scheduled_at?->toISOString(),
            'duration_minutes' => $log->duration_minutes,
            'checked_by' => [
                'id' => $log->checker->id,
                'name' => $log->checker->name,
            ],
        ];
    }

    public function getByDate(string $date): Collection
    {
        return MaintenanceLog::with(['unit.plant', 'checker'])
            ->whereDate('checked_at', Carbon::parse($date)->toDateString())
            ->orderByDesc('checked_at')
            ->get()
            ->groupBy(fn ($log) => $log->unit->plant->name)
            ->map(fn ($logs, $plantName) => [
                'plant' => $plantName,
                'checks' => $logs->map(fn ($log) => $this->formatLog($log))->values(),
            ])
            ->values();
    }
}
