<?php

// app/Services/WorkforceService.php

namespace App\Services;

use App\Enum\RealtimeAction;
use App\Enum\RealtimeModule;
use App\Models\WorkforceRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WorkforceService
{
    public function __construct(
        private RealtimeService $realtime
    ) {}

    // Department metadata — single source of truth, mirrors the frontend config
    private const SECTIONS = [
        'DEPARTMENT' => [
            'opex'             => 'OPEX',
            'hr'               => 'HR',
            'it'               => 'IT',
            'sales'            => 'Sales',
            'finance'          => 'Finance',
            'gen_ops'          => 'Gen Ops',
            'proc_rm'          => 'Procurement - RM',
            'proc_nrm'         => 'Procurement - NRM',
            'proc_local_sales' => 'Procurement - Local Sales',
            'project'          => 'Project',
            'field_ops'        => 'Field Operations',
            'business_ops'     => 'Business Ops',
            'engineering'      => 'Engineering',
        ],
        'DIRECT COST' => [
            'proc_nuts_receiving' => 'Procurement - Nuts Receiving',
            'prod_dry_process'    => 'Production - Dry Process',
            'prod_liquid_line'    => 'Production - Liquid Line',
            'quality'             => 'Quality',
        ],
    ];

    /**
     * Smart bulk upsert — one row per department per day.
     *
     * @param  array<int, array{
     *     department_key: string,
     *     present: int,
     *     headcount: int,
     *     incidents: int,
     * }>  $rows
     * @param  string|null  $recordDate  Y-m-d, defaults to today
     */
    public function storeBulk(array $rows, ?string $recordDate = null): Collection
    {
        $date = $recordDate
            ? Carbon::parse($recordDate)->toDateString()
            : Carbon::today()->toDateString();

        $data = array_map(function (array $row) use ($date): array {
            $section = $this->resolveSection($row['department_key']);
            $label   = $this->resolveLabel($row['department_key']);
            $rate    = $row['headcount'] > 0
                ? round(($row['present'] / $row['headcount']) * 100, 2)
                : null;

            return [
                'department_key'   => $row['department_key'],
                'department_label' => $label,
                'section'          => $section,
                'present'          => $row['present'],
                'headcount'        => $row['headcount'],
                'incidents'        => $row['incidents'],
                'attendance_rate'  => $rate,
                'record_date'      => $date,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }, $rows);

        WorkforceRecord::upsert(
            $data,
            ['department_key', 'record_date'],
            ['present', 'headcount', 'incidents', 'attendance_rate', 'department_label', 'section'],
        );

        $saved = WorkforceRecord::where('record_date', $date)
            ->whereIn('department_key', array_column($rows, 'department_key'))
            ->orderBy('section')
            ->orderBy('department_key')
            ->get();

        $this->realtime->emit(
            RealtimeModule::WORKFORCE,
            RealtimeAction::BULK_CREATED,
            [
                'count' => $saved->count(),
                'ids'   => $saved->pluck('id')->values(),
            ]
        );

        return $saved;
    }

    /**
     * Latest record per department, optionally filtered to a date range.
     */
    public function getLatest(?string $from = null, ?string $to = null): Collection
    {
        return WorkforceRecord::latestPerDepartment($from, $to)
            ->orderBy('workforce_records.section')
            ->orderBy('workforce_records.department_key')
            ->get();
    }

    /**
     * Summary totals for the matching records.
     */
    public function getSummary(?string $from = null, ?string $to = null): array
    {
        $latest = $this->getLatest($from, $to);

        $totalPresent   = $latest->sum('present');
        $totalHeadcount = $latest->sum('headcount');
        $totalIncidents = $latest->sum('incidents');
        $overallRate    = $totalHeadcount > 0
            ? round(($totalPresent / $totalHeadcount) * 100, 2)
            : null;

        return [
            'total_present'    => $totalPresent,
            'total_headcount'  => $totalHeadcount,
            'total_incidents'  => $totalIncidents,
            'attendance_rate'  => $overallRate,
            'department_count' => $latest->count(),
            'by_section'       => $latest
                ->groupBy('section')
                ->map(fn (Collection $rows) => [
                    'present'   => $rows->sum('present'),
                    'headcount' => $rows->sum('headcount'),
                    'incidents' => $rows->sum('incidents'),
                    'rate'      => $rows->sum('headcount') > 0
                        ? round(($rows->sum('present') / $rows->sum('headcount')) * 100, 2)
                        : null,
                ]),
            'from' => $from,
            'to'   => $to,
        ];
    }

    /**
     * History for a single department across a date range — useful for charts.
     */
    public function getDepartmentHistory(
        string $departmentKey,
        ?string $from = null,
        ?string $to = null,
    ): Collection {
        return WorkforceRecord::where('department_key', $departmentKey)
            ->when($from, fn ($q) => $q->where('record_date', '>=', $from))
            ->when($to,   fn ($q) => $q->where('record_date', '<=', $to))
            ->orderBy('record_date')
            ->get();
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function resolveSection(string $key): string
    {
        foreach (self::SECTIONS as $section => $keys) {
            if (array_key_exists($key, $keys)) {
                return $section;
            }
        }

        return 'DEPARTMENT';
    }

    private function resolveLabel(string $key): string
    {
        foreach (self::SECTIONS as $keys) {
            if (isset($keys[$key])) {
                return $keys[$key];
            }
        }

        return $key;
    }
}