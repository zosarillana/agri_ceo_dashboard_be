<?php

namespace App\Services;

use App\Enum\RealtimeAction;
use App\Enum\RealtimeModule;
use App\Models\MaintenanceUnit;
use App\Models\Plant;
use Illuminate\Support\Collection;

class MaintenanceService
{
    public function __construct(
        private RealtimeService $realtime
    ) {}

    /**
     * Return all active plants with their top-level units and nested sub-units.
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
     * Update a unit's status and notes — works for both top-level units AND sub-units.
     * 
     * NOTE: For submitting maintenance checks, use MaintenanceLogService::submitCheck() instead.
     * This method is primarily for manual admin updates.
     */
    public function updateUnit(int $unitId, array $data, bool $skipEmit = false): MaintenanceUnit
    {
        $unit = MaintenanceUnit::with('children')->findOrFail($unitId);

        $unit->update([
            'status' => $data['status'] ?? $unit->status,
            'notes' => $data['notes'] ?? $unit->notes,
            'last_checked_at' => $data['last_checked_at'] ?? $unit->last_checked_at,
            'next_scheduled_at' => $data['next_scheduled_at'] ?? $unit->next_scheduled_at,
        ]);

        // If this is a sub-unit, propagate the new status up to the parent.
        if ($unit->parent_id !== null) {
            $this->propagateStatusToParent($unit->parent_id, $skipEmit);
        }

        // Only emit if not skipped
        if (!$skipEmit) {
            $this->realtime->emit(
                RealtimeModule::MAINTENANCE,
                RealtimeAction::UPDATED,
                [
                    'id' => $unit->id,
                    'plant_id' => $unit->plant_id,
                ]
            );
        }

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

        $unit = MaintenanceUnit::create([
            'plant_id' => $data['plant_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'status' => $data['status'] ?? 'operational',
            'notes' => $data['notes'] ?? null,
            'last_checked_at' => $data['last_checked_at'] ?? null,
            'next_scheduled_at' => $data['next_scheduled_at'] ?? null,
            'sort_order' => $sortOrder,
        ]);

        // A new sub-unit may affect its parent's derived status.
        if ($unit->parent_id !== null) {
            $this->propagateStatusToParent($unit->parent_id);
        }

        $this->realtime->emit(
            RealtimeModule::MAINTENANCE,
            RealtimeAction::CREATED,
            [
                'id' => $unit->id,
                'plant_id' => $unit->plant_id,
            ]
        );

        return $unit;
    }

    /**
     * Soft-delete a unit.
     * After deletion, re-derive the parent's status if this was a sub-unit.
     */
    public function deleteUnit(int $unitId): void
    {
        $unit = MaintenanceUnit::findOrFail($unitId);
        $parentId = $unit->parent_id;

        $unit->delete();

        if ($parentId !== null) {
            $this->propagateStatusToParent($parentId);
        }

        $this->realtime->emit(
            RealtimeModule::MAINTENANCE,
            RealtimeAction::DELETED,
            [
                'id' => $unit->id,
                'plant_id' => $unit->plant_id,
            ]
        );
    }

    /**
     * Summary counts per plant — breaks down top-level units AND sub-units separately.
     */
    public function getStatusSummary(): Collection
    {
        return Plant::active()
            ->with(['allUnits' => fn ($q) => $q->active()])
            ->get()
            ->map(function (Plant $plant) {
                $all = $plant->allUnits;
                $topLevel = $all->whereNull('parent_id');
                $subUnits = $all->whereNotNull('parent_id');

                return [
                    'plant_id' => $plant->id,
                    'plant_name' => $plant->name,
                    'units' => $this->countByStatus($topLevel),
                    'subunits' => $this->countByStatus($subUnits),
                    'overall' => $this->countByStatus($all),
                ];
            });
    }

    /**
     * Detailed sub-unit breakdown for a single top-level unit.
     */
    public function getUnitWithSubUnitSummary(int $unitId): array
    {
        $unit = MaintenanceUnit::active()
            ->with([
                'plant',
                'children' => fn ($q) => $q->active()->orderBy('sort_order'),
            ])
            ->findOrFail($unitId);

        return [
            'unit_id' => $unit->id,
            'unit_name' => $unit->name,
            'plant_name' => $unit->plant->name,
            'status' => $unit->status,
            'subunit_summary' => $this->countByStatus($unit->children),
            'subunits' => $unit->children->map(fn ($child) => [
                'id' => $child->id,
                'name' => $child->name,
                'status' => $child->status,
                'notes' => $child->notes,
                'last_checked_at' => $child->last_checked_at?->toISOString(),
                'next_scheduled_at' => $child->next_scheduled_at?->toISOString(),
            ])->values(),
        ];
    }

    // ─── Parent Status Propagation ────────────────────────────────────────────

    /**
     * Re-derive a parent unit's status from its active children's statuses.
     *
     * Priority order (worst first):
     *   down > maintenance > standby > operational
     */
    private function propagateStatusToParent(int $parentId, bool $skipEmit = false): void
    {
        $parent = MaintenanceUnit::with('children')->find($parentId);

        if (!$parent) {
            return;
        }

        $children = $parent->children()->active()->get();

        if ($children->isEmpty()) {
            return;
        }

        $derived = match (true) {
            $children->contains('status', 'down') => 'down',
            $children->contains('status', 'maintenance') => 'maintenance',
            $children->contains('status', 'standby') => 'standby',
            default => 'operational',
        };

        // Only update if status changed
        if ($parent->status !== $derived) {
            $parent->updateQuietly(['status' => $derived]);
            
            // Emit event for parent update (unless skipped)
            if (!$skipEmit) {
                $this->realtime->emit(
                    RealtimeModule::MAINTENANCE,
                    RealtimeAction::UPDATED,
                    [
                        'id' => $parent->id,
                        'plant_id' => $parent->plant_id,
                        'derived_from_children' => true
                    ]
                );
            }
        }

        // Continue up the chain
        if ($parent->parent_id !== null) {
            $this->propagateStatusToParent($parent->parent_id, $skipEmit);
        }
    }

    // ─── Private Formatters ───────────────────────────────────────────────────

    private function formatPlant(Plant $plant): array
    {
        return [
            'id' => $plant->id,
            'name' => $plant->name,
            'code' => $plant->code,
            'units' => $plant->units->map(fn ($unit) => $this->formatUnit($unit))->values(),
        ];
    }

    private function formatUnit(MaintenanceUnit $unit): array
    {
        $formatted = [
            'id' => $unit->id,
            'name' => $unit->name,
            'status' => $unit->status,
            'notes' => $unit->notes,
            'last_checked_at' => $unit->last_checked_at?->toISOString(),
            'next_scheduled_at' => $unit->next_scheduled_at?->toISOString(),
            'subunit_summary' => $this->countByStatus($unit->children),
        ];

        if ($unit->children->isNotEmpty()) {
            $formatted['subunits'] = $unit->children
                ->map(fn ($child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'status' => $child->status,
                    'notes' => $child->notes,
                    'last_checked_at' => $child->last_checked_at?->toISOString(),
                    'next_scheduled_at' => $child->next_scheduled_at?->toISOString(),
                ])
                ->values();
        }

        return $formatted;
    }

    private function countByStatus(Collection $units): array
    {
        return [
            'total' => $units->count(),
            'operational' => $units->where('status', 'operational')->count(),
            'maintenance' => $units->where('status', 'maintenance')->count(),
            'down' => $units->where('status', 'down')->count(),
            'standby' => $units->where('status', 'standby')->count(),
        ];
    }
}