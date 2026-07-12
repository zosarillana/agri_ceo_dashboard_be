<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Reads the first N rows of *every* sheet in a workbook, as plain arrays
 * with no heading row applied. Used by TemplateDetector to figure out
 * which sheet (and which row within it) matches a saved template, without
 * fully parsing the whole file first.
 */
class SheetScanner implements WithMultipleSheets
{
    /** @var array<string, array<int, array>> sheet name => rows (0-indexed) */
    public array $sheetRows = [];

    public function __construct(protected array $sheetNames, protected int $rowLimit = 25)
    {
    }

    public function sheets(): array
    {
        $imports = [];
        foreach ($this->sheetNames as $name) {
            $imports[$name] = new SingleSheetScanner($name, $this, $this->rowLimit);
        }
        return $imports;
    }
}

class SingleSheetScanner implements ToArray, WithLimit
{
    public function __construct(
        protected string $sheetName,
        protected SheetScanner $parent,
        protected int $rowLimit,
    ) {
    }

    public function limit(): int
    {
        return $this->rowLimit;
    }

    public function array(array $rows): void
    {
        $this->parent->sheetRows[$this->sheetName] = $rows;
    }
}