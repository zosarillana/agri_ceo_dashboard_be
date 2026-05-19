<?php

namespace App\Http\Controllers;

use App\Services\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    public function __construct(
        private readonly MaintenanceService $maintenanceService
    ) {}

    // ─── GET /api/maintenance ─────────────────────────────────────────────────
    /**
     * All plants with their units and sub-units.
     * This is the primary endpoint the frontend dashboard calls.
     */
    public function index(): JsonResponse
    {
        $plants = $this->maintenanceService->getAllPlantsWithUnits();

        return response()->json([
            'data' => $plants,
        ]);
    }

    // ─── GET /api/maintenance/summary ────────────────────────────────────────
    /**
     * Status counts per plant — for summary widgets.
     */
    public function summary(): JsonResponse
    {
        $summary = $this->maintenanceService->getStatusSummary();

        return response()->json([
            'data' => $summary,
        ]);
    }

    // ─── GET /api/maintenance/plants/{plant} ──────────────────────────────────
    /**
     * Single plant with its units.
     */
    public function showPlant(int $plantId): JsonResponse
    {
        $plant = $this->maintenanceService->getPlantWithUnits($plantId);

        return response()->json([
            'data' => $plant,
        ]);
    }

    // ─── POST /api/maintenance/units ─────────────────────────────────────────
    /**
     * Create a new unit or sub-unit.
     * Pass parent_id to create a sub-unit under an existing unit.
     */
    public function storeUnit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plant_id'           => ['required', 'integer', 'exists:plants,id'],
            'parent_id'          => ['nullable', 'integer', 'exists:maintenance_units,id'],
            'name'               => ['required', 'string', 'max:255'],
            'status'             => ['nullable', Rule::in(['operational', 'maintenance', 'down', 'standby'])],
            'notes'              => ['nullable', 'string'],
            'last_checked_at'    => ['nullable', 'date'],
            'next_scheduled_at'  => ['nullable', 'date'],
        ]);

        $unit = $this->maintenanceService->createUnit($validated);

        return response()->json([
            'message' => 'Unit created successfully.',
            'data'    => $unit,
        ], 201);
    }

    // ─── PATCH /api/maintenance/units/{unit} ──────────────────────────────────
    /**
     * Update status, notes, or scheduled dates for a unit or sub-unit.
     */
    public function updateUnit(Request $request, int $unitId): JsonResponse
    {
        $validated = $request->validate([
            'status'             => ['sometimes', Rule::in(['operational', 'maintenance', 'down', 'standby'])],
            'notes'              => ['sometimes', 'nullable', 'string'],
            'last_checked_at'    => ['sometimes', 'nullable', 'date'],
            'next_scheduled_at'  => ['sometimes', 'nullable', 'date'],
        ]);

        $unit = $this->maintenanceService->updateUnit($unitId, $validated);

        return response()->json([
            'message' => 'Unit updated successfully.',
            'data'    => $unit,
        ]);
    }

    // ─── DELETE /api/maintenance/units/{unit} ─────────────────────────────────
    /**
     * Soft-delete a unit.
     */
    public function destroyUnit(int $unitId): JsonResponse
    {
        $this->maintenanceService->deleteUnit($unitId);

        return response()->json([
            'message' => 'Unit deleted successfully.',
        ]);
    }
}