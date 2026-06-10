<?php

namespace App\Services;

use App\Enum\RealtimeAction;
use App\Enum\RealtimeModule;
use App\Models\ProductionEntry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
            $this->realtime->emit(
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

        $this->realtime->emit(
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

        $this->realtime->emit(
            RealtimeModule::PRODUCTION,
            RealtimeAction::DELETED,
            ['id' => $id]
        );

        return $deleted;
    }

    public function saveDailyEntries(array $entries): Collection
    {
        $saved = DB::transaction(function () use ($entries) {
            return collect($entries)
                ->map(fn (array $entry) => $this->create($entry, silent: true));
        });

        $this->realtime->emit(
            RealtimeModule::PRODUCTION,
            RealtimeAction::BULK_CREATED,
            [
                'count' => $saved->count(),
                'ids'   => $saved->pluck('id')->values(),
            ]
        );

        return $saved;
    }
}