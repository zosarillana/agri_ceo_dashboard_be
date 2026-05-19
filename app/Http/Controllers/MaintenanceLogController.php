<?php

namespace App\Http\Controllers;

use App\Services\MaintenanceLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceLogController extends Controller
{
    public function __construct(
        private readonly MaintenanceLogService $logService
    ) {}

    // ─── POST /api/maintenance/units/{unit}/check ─────────────────────────────
    /**
     * Submit a daily check for a unit.
     * Creates a log entry AND updates the unit's current status snapshot.
     *
     * Required: status
     * Optional: notes, checked_at, next_scheduled_at, duration_minutes
     */
    public function submitCheck(Request $request, int $unitId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['operational', 'maintenance', 'down', 'standby'])],
            'notes' => ['nullable', 'string'],
            'checked_at' => ['nullable', 'date'],
            'next_scheduled_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $log = $this->logService->submitCheck($unitId, $validated);

        return response()->json([
            'message' => 'Check submitted successfully.',
            'data' => $log,
        ], 201);
    }

    // ─── GET /api/maintenance/units/{unit}/logs ───────────────────────────────
    /**
     * Full log history for a single unit, paginated.
     */
    public function unitHistory(int $unitId): JsonResponse
    {
        $logs = $this->logService->getUnitHistory($unitId);

        return response()->json([
            'data' => $logs,
        ]);
    }

    // ─── GET /api/maintenance/units/{unit}/logs/history ──────────────────────
    /**
     * Status history for a unit over a date range.
     * Query params: from (date), to (date)
     */
    public function unitStatusHistory(Request $request, int $unitId): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $history = $this->logService->getUnitStatusHistory(
            $unitId,
            $validated['from'],
            $validated['to']
        );

        return response()->json([
            'data' => $history,
        ]);
    }

    // ─── GET /api/maintenance/logs/today ─────────────────────────────────────
    /**
     * All checks submitted today, grouped by plant.
     * Main daily monitoring overview endpoint.
     */
    public function today(): JsonResponse
    {
        $checks = $this->logService->getTodaysChecks();

        return response()->json([
            'data' => $checks,
        ]);
    }

    // ─── GET /api/maintenance/logs/unchecked ─────────────────────────────────
    /**
     * Units that have NOT been checked today.
     */
    public function unchecked(): JsonResponse
    {
        $units = $this->logService->getUncheckedToday();

        return response()->json([
            'data' => $units,
        ]);
    }

    // ─── GET /api/maintenance/logs/completion ────────────────────────────────
    /**
     * Daily check completion % per plant.
     * e.g. Unit 1: 5/6 checked (83%)
     */
    public function completion(): JsonResponse
    {
        $summary = $this->logService->getDailyCompletionSummary();

        return response()->json([
            'data' => $summary,
        ]);
    }

    // ─── GET /api/maintenance/logs/user/{user} ────────────────────────────────
    /**
     * All checks submitted by a specific user, paginated.
     */
    public function userHistory(int $userId): JsonResponse
    {
        $logs = $this->logService->getUserHistory($userId);

        return response()->json([
            'data' => $logs,
        ]);
    }

    public function byDate(string $date)
    {
        return response()->json([
            'data' => $this->logService->getByDate($date),
        ]);
    }
}
