<?php

// app/Models/WorkforceRecord.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkforceRecord extends Model
{
    protected $fillable = [
        'department_key',
        'department_label',
        'section',
        'present',
        'headcount',
        'incidents',
        'attendance_rate',
        'record_date',
    ];

    protected $casts = [
        'record_date' => 'date',
        'present' => 'integer',
        'headcount' => 'integer',
        'incidents' => 'integer',
        'attendance_rate' => 'float',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────

    /**
     * Latest record per department, optionally within a date range.
     * Mirrors Sale::latestPerProduct().
     */
    // app/Models/WorkforceRecord.php

    public function scopeLatestPerDepartment(
        Builder $query,
        ?string $from = null,
        ?string $to = null,
    ): Builder {
        $sub = static::query()
            ->selectRaw('department_key, MAX(record_date) as max_date')
            ->when($from, fn ($q) => $q->where('record_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('record_date', '<=', $to))
            ->groupBy('department_key');

        return $query
            ->select('workforce_records.*')           // ← explicitly select only main table columns
            ->joinSub($sub, 'latest', function ($join) {
                $join->on('workforce_records.department_key', '=', 'latest.department_key')
                    ->on('workforce_records.record_date', '=', 'latest.max_date');
            });
    }
}
