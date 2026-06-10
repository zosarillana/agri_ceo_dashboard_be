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

    /**
     * Submit a daily check for a unit.
     * Creates a log entry AND updates the unit's current status snapshot.
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

    /**
     * Status history for a unit over a date range.
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

    /**
     * All checks submitted today, grouped by plant.
     */
    public function today(): JsonResponse
    {
        $checks = $this->logService->getTodaysChecks();

        return response()->json([
            'data' => $checks,
        ]);
    }

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

    /**
     * Daily check completion % per plant.
     */
    public function completion(): JsonResponse
    {
        $summary = $this->logService->getDailyCompletionSummary();

        return response()->json([
            'data' => $summary,
        ]);
    }

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

    /**
     * Get checks by specific date.
     */
    public function byDate(string $date): JsonResponse
    {
        return response()->json([
            'data' => $this->logService->getByDate($date),
        ]);
    }
}