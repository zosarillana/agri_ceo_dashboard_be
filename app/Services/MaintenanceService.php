<?php

namespace App\Services;

use App\Models\MaintenanceUnit;
use App\Models\Plant;
use Illuminate\Support\Collection;

class MaintenanceService
{
    /**
     * Return all active plants with their top-level units and nested sub-units,
     * shaped exactly as the frontend expects.
     *
     * Response shape per plant:
     * {
     *   id, name, code,
     *   units: [
     *     { id, name, status, notes, last_checked_at, next_scheduled_at,
     *       subunits: [ { id, name, status } ]
     *     }
     *   ]
     * }
     */
    public function getAllPlantsWithUnits(): Collection
    {
        return Plant::active()
            ->with([
                'units' => function ($q) {
                    $q->active()->with([
                        'children' => fn ($q) => $q->active()->orderBy('sort_order'),
                    ]);
                },
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Plant $plant) => $this->formatPlant($plant));
    }

    /**
     * Return a single plant with its units.
     */
    public function getPlantWithUnits(int $plantId): array
    {
        $plant = Plant::active()
            ->with([
                'units' => fn ($q) => $q->active()->with([
                    'children' => fn ($q) => $q->active()->orderBy('sort_order'),
                ]),
            ])
            ->findOrFail($plantId);

        return $this->formatPlant($plant);
    }

    /**
     * Update a unit's status and notes.
     */
    public function updateUnit(int $unitId, array $data): MaintenanceUnit
    {
        $unit = MaintenanceUnit::findOrFail($unitId);

        $unit->update([
            'status'             => $data['status']             ?? $unit->status,
            'notes'              => $data['notes']              ?? $unit->notes,
            'last_checked_at'    => $data['last_checked_at']    ?? $unit->last_checked_at,
            'next_scheduled_at'  => $data['next_scheduled_at']  ?? $unit->next_scheduled_at,
        ]);

        return $unit->fresh(['plant', 'children']);
    }

    /**
     * Create a new unit (or sub-unit when parent_id is provided).
     */
    public function createUnit(array $data): MaintenanceUnit
    {
        $sortOrder = MaintenanceUnit::where('plant_id', $data['plant_id'])
            ->where('parent_id', $data['parent_id'] ?? null)
            ->max('sort_order') + 1;

        return MaintenanceUnit::create([
            'plant_id'           => $data['plant_id'],
            'parent_id'          => $data['parent_id']          ?? null,
            'name'               => $data['name'],
            'status'             => $data['status']             ?? 'operational',
            'notes'              => $data['notes']              ?? null,
            'last_checked_at'    => $data['last_checked_at']    ?? null,
            'next_scheduled_at'  => $data['next_scheduled_at']  ?? null,
            'sort_order'         => $sortOrder,
        ]);
    }

    /**
     * Soft-delete a unit. Children are nullOnDelete (they become orphaned/top-level).
     * If you want cascading deletes, change the migration and remove this guard.
     */
    public function deleteUnit(int $unitId): void
    {
        MaintenanceUnit::findOrFail($unitId)->delete();
    }

    /**
     * Summary counts per plant — useful for dashboard widgets.
     * Returns: [ plant_id => [ operational => N, maintenance => N, down => N ] ]
     */
    public function getStatusSummary(): Collection
    {
        return Plant::active()
            ->with(['allUnits' => fn ($q) => $q->active()])
            ->get()
            ->map(function (Plant $plant) {
                $units = $plant->allUnits;
                return [
                    'plant_id'    => $plant->id,
                    'plant_name'  => $plant->name,
                    'total'       => $units->count(),
                    'operational' => $units->where('status', 'operational')->count(),
                    'maintenance' => $units->where('status', 'maintenance')->count(),
                    'down'        => $units->where('status', 'down')->count(),
                    'standby'     => $units->where('status', 'standby')->count(),
                ];
            });
    }

    // ─── Private Formatters ───────────────────────────────────────────────────

    private function formatPlant(Plant $plant): array
    {
        return [
            'id'    => $plant->id,
            'name'  => $plant->name,
            'code'  => $plant->code,
            'units' => $plant->units->map(fn ($unit) => $this->formatUnit($unit))->values(),
        ];
    }

    private function formatUnit(MaintenanceUnit $unit): array
    {
        $formatted = [
            'id'                 => $unit->id,
            'name'               => $unit->name,
            'status'             => $unit->status,
            'notes'              => $unit->notes,
            'last_checked_at'    => $unit->last_checked_at?->toISOString(),
            'next_scheduled_at'  => $unit->next_scheduled_at?->toISOString(),
        ];

        if ($unit->children->isNotEmpty()) {
            $formatted['subunits'] = $unit->children
                ->map(fn ($child) => [
                    'id'     => $child->id,
                    'name'   => $child->name,
                    'status' => $child->status,
                ])
                ->values();
        }

        return $formatted;
    }
}