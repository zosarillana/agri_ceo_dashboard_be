<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductionEntry;
use Illuminate\Support\Str;

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

            ProductionEntry::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'production_date' => $productionDate,
                ],
                [
                    'actual_output' => $actual ?? 0,
                    'target_output' => $target ?? $product->default_target,
                    'remarks' => is_string($remarks) ? trim($remarks) : $remarks,
                ]
            );

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