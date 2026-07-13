<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductionEntry;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Takes decoded import rows (before json_encode) plus the template's
 * column_map roles, and syncs matched rows into production_entries.
 *
 * Only rows whose product_name-role column matches an EXISTING Product
 * (by slug or exact name) get written — sheet rows that are section
 * headers, subtotal lines, or notes (which don't correspond to a real
 * product) are counted as "skipped", not errored.
 *
 * Re-importing the same product + date updates the existing entry
 * instead of creating a duplicate (updateOrCreate on product_id +
 * production_date), since production_entries is a real business table,
 * not a raw import log like import_rows.
 */
class ProductionEntrySync
{
    protected int $matched = 0;
    protected int $skipped = 0;
    protected array $unmatchedNames = [];

    public function sync(array $columnMap, array $rows, string $productionDate): void
    {
        $roles = collect($columnMap)->keyBy('db_column');

        $nameCol    = $this->columnForRole($roles, 'product_name');
        $actualCol  = $this->columnForRole($roles, 'actual_output');
        $targetCol  = $this->columnForRole($roles, 'target_output');
        $remarksCol = $this->columnForRole($roles, 'remarks');

        if (! $nameCol) {
            // Template has no product_name column mapped — nothing to sync.
            return;
        }

        foreach ($rows as $row) {
            $rawName = trim((string) ($row[$nameCol] ?? ''));
            if ($rawName === '') {
                continue;
            }

            $slug = Str::slug($rawName);
            $product = Product::where('slug', $slug)
                ->orWhere('name', $rawName)
                ->first();

            if (! $product) {
                $this->skipped++;
                $this->unmatchedNames[] = $rawName;
                continue;
            }

            $actual = $this->toDecimal($actualCol ? ($row[$actualCol] ?? null) : null);
            $target = $this->toDecimal($targetCol ? ($row[$targetCol] ?? null) : null);
            $remarks = $remarksCol ? ($row[$remarksCol] ?? null) : null;

            // firstOrNew (not updateOrCreate) so we can tell whether this
            // row is brand new, and only backdate created_at in that case
            // — re-importing an existing entry should still bump
            // updated_at to "now" (real edit time) without disturbing
            // when the row was first created.
            $entry = ProductionEntry::firstOrNew([
                'product_id' => $product->id,
                'production_date' => $productionDate,
            ]);

            $isNew = ! $entry->exists;

            $entry->actual_output = $actual ?? 0;
            $entry->target_output = $target ?? $product->default_target;
            $entry->remarks = is_string($remarks) ? trim($remarks) : $remarks;

            if ($isNew) {
                // Use the sheet's own date, not the moment the import ran —
                // relevant for "import all sheets" batches where each sheet
                // represents a different historical day (JULY 1, JULY 2, ...)
                // imported all at once today.
                $entry->created_at = Carbon::parse($productionDate)->startOfDay();
            }

            $entry->save();

            $this->matched++;
        }
    }

    public function stats(): array
    {
        return [
            'matched' => $this->matched,
            'skipped' => $this->skipped,
            'unmatched_names' => array_values(array_unique($this->unmatchedNames)),
        ];
    }

    protected function columnForRole($rolesKeyedByDbColumn, string $role): ?string
    {
        $match = $rolesKeyedByDbColumn->first(fn ($col) => ($col['role'] ?? null) === $role);
        return $match['db_column'] ?? null;
    }

    protected function toDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $clean = str_replace(',', '', (string) $value);
        return is_numeric($clean) ? (float) $clean : null;
    }
}