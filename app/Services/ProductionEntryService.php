<?php

namespace App\Services;

use App\Enum\RealtimeAction;
use App\Enum\RealtimeModule;
use App\Models\ProductionEntry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionEntryService
{
    public function __construct(
        private RealtimeService $realtime
    ) {}

    public function getAll(): Collection
    {
        return ProductionEntry::with('product')
            ->latest('production_date')
            ->get();
    }

    public function getByDate(string $date): Collection
    {
        return ProductionEntry::with('product')
            ->whereDate('production_date', Carbon::parse($date)->toDateString())
            ->get();
    }

    public function create(array $data, bool $silent = false): ProductionEntry
    {
        $entry = ProductionEntry::updateOrCreate(
            [
                'product_id'      => $data['product_id'],
                'production_date' => $data['production_date'],
            ],
            [
                'actual_output' => $data['actual_output'],
                'target_output' => $data['target_output'],
                'remarks'       => $data['remarks'] ?? null,
            ]
        );

        if (! $silent) {
            $this->emitSafely(
                RealtimeModule::PRODUCTION,
                $entry->wasRecentlyCreated ? RealtimeAction::CREATED : RealtimeAction::UPDATED,
                ['id' => $entry->id]
            );
        }

        return $entry;
    }

    public function update(ProductionEntry $productionEntry, array $data): ProductionEntry
    {
        $productionEntry->update($data);

        $updated = $productionEntry->fresh();

        $this->emitSafely(
            RealtimeModule::PRODUCTION,
            RealtimeAction::UPDATED,
            ['id' => $updated->id]
        );

        return $updated;
    }

    public function delete(ProductionEntry $productionEntry): bool
    {
        $id      = $productionEntry->id;
        $deleted = $productionEntry->delete();

        $this->emitSafely(
            RealtimeModule::PRODUCTION,
            RealtimeAction::DELETED,
            ['id' => $id]
        );

        return $deleted;
    }

    public function saveDailyEntries(array $entries): Collection
    {
        // Deduplicate entries by product_id + production_date so that
        // duplicate pairs in the same batch don't cause a deadlock or
        // unique-constraint violation inside the transaction.
        $deduped = collect($entries)
            ->unique(fn (array $e) => $e['product_id'] . '_' . $e['production_date'])
            ->values()
            ->all();

        $saved = DB::transaction(function () use ($deduped) {
            return collect($deduped)
                ->map(fn (array $entry) => $this->create($entry, silent: true));
        });

        // Emit outside the transaction so a realtime failure never rolls
        // back data that was already committed successfully.
        $this->emitSafely(
            RealtimeModule::PRODUCTION,
            RealtimeAction::BULK_CREATED,
            [
                'count' => $saved->count(),
                'ids'   => $saved->pluck('id')->values(),
            ]
        );

        return $saved;
    }

    /**
     * Emit a realtime event without letting a broadcasting failure
     * bubble up and kill an otherwise successful response.
     */
    private function emitSafely(
        RealtimeModule $module,
        RealtimeAction $action,
        array $payload = []
    ): void {
        try {
            $this->realtime->emit($module, $action, $payload);
        } catch (\Throwable $e) {
            Log::warning('Realtime emit failed — data was saved successfully.', [
                'module'  => $module->value ?? $module->name,
                'action'  => $action->value ?? $action->name,
                'payload' => $payload,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}