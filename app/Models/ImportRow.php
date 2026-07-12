<?php

namespace App\Models;  // <-- ADD THIS LINE AT THE TOP

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_template_id',
        'sheet_name',
        'source_file',
        'row_number',
        'data',
        'imported_at',
    ];

    protected $casts = [
        'data' => 'array',
        'imported_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ImportTemplate::class, 'import_template_id');
    }

    /**
     * Get a specific value from the data array
     */
    public function getValue(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if this row contains actual numeric data
     */
    public function hasNumericData(): bool
    {
        foreach ($this->data as $value) {
            if (is_numeric(str_replace(',', '', (string) $value))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all numeric values from the row
     */
    public function getNumericValues(): array
    {
        $values = [];
        foreach ($this->data as $key => $value) {
            $cleaned = str_replace(',', '', (string) $value);
            if (is_numeric($cleaned)) {
                $values[$key] = (float) $cleaned;
            }
        }
        return $values;
    }
}