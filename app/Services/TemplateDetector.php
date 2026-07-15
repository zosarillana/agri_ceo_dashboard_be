<?php

namespace App\Services;

use App\Models\ImportTemplate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TemplateDetector
{
    public function detect(string $filePath): ?array
    {
        $spreadsheet = IOFactory::load($filePath);

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);

            if (! $sheet) {
                continue;
            }

            $highestColumn = $sheet->getHighestColumn();

            // Scan the first 30 rows looking for a matching header
            for ($headerRow = 1; $headerRow <= 30; $headerRow++) {

                $headers = $sheet->rangeToArray(
                    "A{$headerRow}:{$highestColumn}{$headerRow}",
                    null,
                    true,
                    false
                )[0];

                $headers = array_values(array_filter(
                    array_map(fn ($v) => trim((string) $v), $headers),
                    fn ($v) => $v !== ''
                ));

                if (empty($headers)) {
                    continue;
                }

                $template = ImportTemplate::findMatch($headers);

                if ($template) {
                    return [
                        'template'  => $template,
                        'sheet'     => $sheetName,
                        'headerRow' => $headerRow,
                        'headers'   => $headers,
                    ];
                }
            }
        }

        return null;
    }

    public function sheetNames(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);

        return $spreadsheet->getSheetNames();
    }
}