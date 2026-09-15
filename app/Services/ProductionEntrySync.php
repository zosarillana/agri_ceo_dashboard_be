<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductionEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

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
 * instead of creating a duplicate, since production_entries is a real
 * business table, not a raw import log like import_rows.
 *
 * Each row is processed independently: if one row throws (bad date,
 * DB constraint, unexpected value shape, whatever), it's caught, logged
 * with full context, and recorded in errors() — the rest of the rows
 * still get synced. A single bad row no longer takes down the whole
 * import as an opaque 500.
 */
class ProductionEntrySync
{
    protected int $matched = 0;

    protected int $skipped = 0;

    protected array $unmatchedNames = [];

    /** @var array<int, array{row_name: string, product_id: ?int, message: string}> */
    protected array $errors = [];

    public function sync(array $columnMap, array $rows, string $productionDate): void
    {
        $roles = collect($columnMap)->keyBy('db_column');

        $nameCol = $this->columnForRole($roles, 'product_name');
        $actualCol = $this->columnForRole($roles, 'actual_output');
        $targetCol = $this->columnForRole($roles, 'target_output');
        $dlyTargetCol = $this->columnForRole($roles, 'dly_target');
        $dlyYieldCol = $this->columnForRole($roles, 'dly_yield');
        $mtdTargetCol = $this->columnForRole($roles, 'mtd_target');
        $mtdYieldCol = $this->columnForRole($roles, 'mtd_yield');
        $remarksCol = $this->columnForRole($roles, 'remarks');

        if (! $nameCol) {
            // Template has no product_name column mapped — nothing to sync.
            return;
        }

        foreach ($rows as $rowIndex => $row) {
            $rawName = trim((string) ($row[$nameCol] ?? ''));
            if ($rawName === '') {
                continue;
            }

            try {
                $this->syncRow(
                    $row,
                    $rawName,
                    $actualCol,
                    $targetCol,
                    $dlyTargetCol,
                    $dlyYieldCol,
                    $mtdTargetCol,
                    $mtdYieldCol,
                    $remarksCol,
                    $productionDate,
                );
            } catch (Throwable $e) {
                $this->errors[] = [
                    'row_name' => $rawName,
                    'product_id' => null,
                    'message' => $e->getMessage(),
                ];

                Log::error('[ProductionEntrySync] row failed', [
                    'row_index' => $rowIndex,
                    'row_name' => $rawName,
                    'production_date' => $productionDate,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    // Full row data, so a broken value is visible without
                    // needing to reproduce the import to find it.
                    'row_data' => $row,
                ]);

                // Deliberately no rethrow — one bad row shouldn't abort
                // every other row in the same import.
            }
        }
    }

    /**
     * Handles exactly one row. Split out from sync() so the try/catch in
     * the loop wraps everything that could plausibly throw — product
     * lookup, decimal parsing, and the actual save — as a single unit
     * per row.
     */
    protected function syncRow(
        array $row,
        string $rawName,
        ?string $actualCol,
        ?string $targetCol,
        ?string $dlyTargetCol,
        ?string $dlyYieldCol,
        ?string $mtdTargetCol,
        ?string $mtdYieldCol,
        ?string $remarksCol,
        string $productionDate,
    ): void {
        $slug = Str::slug($rawName);
        $product = Product::where('slug', $slug)
            ->orWhere('name', $rawName)
            ->first();

        if (! $product) {
            $this->skipped++;
            $this->unmatchedNames[] = $rawName;

            return;
        }

        $actual = $this->toDecimal($actualCol ? ($row[$actualCol] ?? null) : null);
        $target = $this->toDecimal($targetCol ? ($row[$targetCol] ?? null) : null);
        $dlyTarget = $this->toDecimal($dlyTargetCol ? ($row[$dlyTargetCol] ?? null) : null);
        $dlyYield = $this->toDecimal($dlyYieldCol ? ($row[$dlyYieldCol] ?? null) : null);
        $mtdTarget = $this->toDecimal($mtdTargetCol ? ($row[$mtdTargetCol] ?? null) : null);
        $mtdYield = $this->toDecimal($mtdYieldCol ? ($row[$mtdYieldCol] ?? null) : null);
        $remarks = $remarksCol ? ($row[$remarksCol] ?? null) : null;

        // Excel stores percentage-formatted cells as their underlying fraction
        // (31.57% is stored internally as 0.3157), and the Excel reader returns
        // that raw fraction rather than the formatted "31.57%" string. Convert
        // to a human-readable percentage before saving.
        if ($dlyYield !== null) {
            $dlyYield *= 100;
        }
        if ($mtdYield !== null) {
            $mtdYield *= 100;
        }

        try {
            // firstOrNew (not updateOrCreate) so we can tell whether this
            // row is brand new, and only backdate created_at in that case
            // — re-importing an existing entry should still bump
            // updated_at to "now" (real edit time) without disturbing
            // when the row was first created.
            $entry = ProductionEntry::firstOrNew([
                'product_id' => $product->id,
                'production_date' => $productionDate,
            ]);
        } catch (Throwable $e) {
            // Most likely cause here: $productionDate isn't a value MySQL
            // accepts as a DATE (bad format from the frontend, or an empty
            // string slipping through). Wrap with product context before
            // rethrowing so the outer catch logs something actionable
            // instead of a bare "Data truncated" / "Invalid date" message.
            throw new \RuntimeException(
                "Could not look up entry for product #{$product->id} ({$product->name}) on date '{$productionDate}': ".$e->getMessage(),
                previous: $e,
            );
        }

        $isNew = ! $entry->exists;

        $entry->actual_output = $actual ?? 0;
        $entry->target_output = $target ?? $product->default_target;
        $entry->dly_target = $dlyTarget ?? 0;
        $entry->dly_yield = $dlyYield ?? 0;
        $entry->mtd_target = $mtdTarget ?? 0;
        $entry->mtd_yield = $mtdYield ?? 0;
        $entry->remarks = is_string($remarks) ? trim($remarks) : $remarks;

        if ($isNew) {
            // Use the sheet's own date, not the moment the import ran —
            // relevant for "import all sheets" batches where each sheet
            // represents a different historical day (JULY 1, JULY 2, ...)
            // imported all at once today.
            $entry->created_at = Carbon::parse($productionDate)->startOfDay();
        }

        try {
            $entry->save();
        } catch (Throwable $e) {
            // Most likely causes here: a DB constraint (e.g. a unique
            // index on product_id+production_date colliding with a race,
            // or actual_output/target_output overflowing the decimal
            // column's precision on an unusually large sheet value).
            throw new \RuntimeException(
                "Could not save entry for product #{$product->id} ({$product->name}) on date '{$productionDate}' ".
                "(actual={$actual}, target={$target}): ".$e->getMessage(),
                previous: $e,
            );
        }

        $this->matched++;
    }

    public function stats(): array
    {
        return [
            'matched' => $this->matched,
            'skipped' => $this->skipped,
            'unmatched_names' => array_values(array_unique($this->unmatchedNames)),
            'errors' => $this->errors,
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
        $clean = str_replace([',', '%'], '', (string) $value);

        return is_numeric($clean) ? (float) $clean : null;
    }
}