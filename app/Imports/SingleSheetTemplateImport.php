<?php

namespace App\Imports;

use App\Models\ImportRow;
use App\Models\ImportTemplate;
use App\Services\ProductionEntrySync;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class SingleSheetTemplateImport implements ToCollection, WithCalculatedFormulas
{
    public int $importedCount = 0;
    protected ProductionEntrySync $productionSync;

    public function __construct(
        protected ImportTemplate $template,
        protected string $sheetName,
        protected ?string $sourceFile = null,
        protected ?int $headerRowOverride = null,
        protected ?array $selectedRowIndices = null,
        protected ?string $productionDate = null,
    ) {
        $this->productionSync = new ProductionEntrySync();
    }

    public function headingRow(): int
    {
        return $this->headerRowOverride ?? $this->template->header_row;
    }

    public function collection(Collection $sheet): void
    {
        $now = now();
        $headerRow = $this->headingRow();

        $dataRows = $sheet->slice($headerRow)
            ->filter(fn ($row) => $row->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty())
            ->values();

        $selected = $this->selectedRowIndices;
        $decodedRows = []; // kept alongside for the production_entries sync below

        $records = $dataRows
            ->filter(fn ($row, $index) => $selected === null || in_array($index, $selected, true))
            ->map(function (Collection $row, int $index) use ($now, $headerRow, &$decodedRows) {
                $data = [];

                foreach ($this->template->column_map as $col) {
                    $colIndex = Coordinate::columnIndexFromString($col['letter']) - 1;
                    $value = $row->get($colIndex);

                    if (is_string($value)) {
                        $value = trim($value);
                    }

                    $data[$col['db_column']] = $value;
                }

                $decodedRows[] = $data;

                return [
                    'import_template_id' => $this->template->id,
                    'sheet_name' => $this->sheetName,
                    'source_file' => $this->sourceFile,
                    'row_number' => $headerRow + 1 + $index,
                    'data' => json_encode($data),
                    'imported_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values();

        $this->importedCount = $records->count();

        if ($records->isNotEmpty()) {
            $records->chunk(500)->each(fn ($chunk) => ImportRow::insert($chunk->all()));
        }

        // Sync into production_entries — only runs if a production date was
        // passed in AND the template has at least one column mapped to the
        // product_name role. If the sync never ran before now, it's because
        // this method never called it; check that below with
        // production_sync in the API response, not just this log line.
        if ($this->productionDate) {
            $this->productionSync->sync(
                $this->template->column_map,
                $decodedRows,
                $this->productionDate,
            );
        }

        \Illuminate\Support\Facades\Log::info('Import completed', [
            'template_id' => $this->template->id,
            'sheet_name' => $this->sheetName,
            'rows_imported' => $this->importedCount,
            'header_row' => $headerRow,
            'row_selection_applied' => $selected !== null,
            'production_date' => $this->productionDate,
            'production_sync' => $this->productionSync->stats(),
        ]);
    }

    public function productionSyncStats(): array
    {
        return $this->productionSync->stats();
    }
}