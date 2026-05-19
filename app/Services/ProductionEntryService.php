<?php

namespace App\Services;

use App\Models\ProductionEntry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ProductionEntryService
{
    public function getAll()
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

    public function create(array $data): ProductionEntry
    {
        return ProductionEntry::updateOrCreate(
            [
                'product_id' => $data['product_id'],
                'production_date' => $data['production_date'],
            ],
            [
                'actual_output' => $data['actual_output'],
                'target_output' => $data['target_output'],
                'remarks' => $data['remarks'] ?? null,
            ]
        );
    }

    public function update(
        ProductionEntry $productionEntry,
        array $data
    ): ProductionEntry {
        $productionEntry->update($data);

        return $productionEntry->fresh();
    }

    public function delete(ProductionEntry $productionEntry): bool
    {
        return $productionEntry->delete();
    }

    public function saveDailyEntries(array $entries)
    {
        foreach ($entries as $entry) {
            ProductionEntry::updateOrCreate(
                [
                    'product_id' => $entry['product_id'],
                    'production_date' => $entry['production_date'],
                ],
                [
                    'actual_output' => $entry['actual_output'],
                    'target_output' => $entry['target_output'],
                    'remarks' => $entry['remarks'] ?? null,
                ]
            );
        }
    }
}
