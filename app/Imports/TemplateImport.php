<?php

namespace App\Imports;

use App\Models\ImportTemplate;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Parses a spreadsheet server-side using a saved ImportTemplate: which row
 * holds the headers, and how each header label maps to a database column.
 *
 * Workbooks can contain several sheets, but only one is meant to be
 * imported. WithMultipleSheets lets us process *only* that sheet by name
 * instead of every sheet in the file.
 *
 * $headerRowOverride lets the auto-detector (see TemplateDetector) tell us
 * exactly which row it found the headers on in *this* file, in case it
 * doesn't line up with the template's originally-saved header_row (e.g. an
 * extra note row got inserted above the table this time).
 *
 * $productionDate, when provided, triggers a sync into production_entries
 * after the raw import_rows log is written — see SingleSheetTemplateImport
 * ::collection() and App\Services\ProductionEntrySync.
 *
 * IMPORTANT: this relies on raw (non-slugified) header labels as array
 * keys, so make sure config/excel.php has:
 *   'imports' => ['heading_row' => ['formatter' => null]]
 * (see the README for why).
 */
class TemplateImport implements WithMultipleSheets
{
    public ?SingleSheetTemplateImport $sheetImport = null;

    public function __construct(
        protected ImportTemplate $template,
        protected string $sheetName,
        protected ?string $sourceFile = null,
        protected ?int $headerRowOverride = null,
        protected ?array $selectedRowIndices = null,
        protected ?string $productionDate = null,
    ) {
    }

    public function sheets(): array
    {
        $this->sheetImport = new SingleSheetTemplateImport(
            $this->template,
            $this->sheetName,
            $this->sourceFile,
            $this->headerRowOverride,
            $this->selectedRowIndices,
            $this->productionDate,
        );

        return [$this->sheetName => $this->sheetImport];
    }

    public function importedCount(): int
    {
        return $this->sheetImport?->importedCount ?? 0;
    }

    public function productionSyncStats(): array
    {
        return $this->sheetImport?->productionSyncStats() ?? [
            'matched' => 0,
            'skipped' => 0,
            'unmatched_names' => [],
        ];
    }
}