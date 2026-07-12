<?php

namespace App\Services;

use App\Models\ImportTemplate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TemplateDetector
{
    public function detect(string $filePath): ?array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheetNames = $spreadsheet->getSheetNames();

        foreach ($sheetNames as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $highestColumn = $sheet->getHighestColumn();
            $headerRow = 1; // Default to first row, but you might want to detect this
            
            // Get headers from the first row
            $headers = $sheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE)[0];
            $headers = array_filter(array_map('trim', $headers));
            
            // Try to find a matching template
            $template = ImportTemplate::findMatch($headers);
            
            if ($template) {
                return [
                    'template' => $template,
                    'sheet' => $sheetName,
                    'headerRow' => $headerRow,
                    'headers' => $headers,
                ];
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