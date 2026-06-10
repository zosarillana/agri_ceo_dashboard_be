<?php
// app/Services/QcService.php

namespace App\Services;

use App\Enum\RealtimeAction;
use App\Enum\RealtimeModule;
use App\Models\QcRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class QcService
{
    public function __construct(
        private RealtimeService $realtime
    ) {}

    /**
     * Smart bulk save: upsert by (product_id, qc_date).
     *
     * @param  array<int, array{product_id: int, tested: int, passed: int}>  $rows
     * @param  string|null  $qcDate  Y-m-d, defaults to today
     */
    public function storeBulk(array $rows, ?string $qcDate = null): Collection
    {
        $date = $qcDate
            ? Carbon::parse($qcDate)->toDateString()
            : Carbon::today()->toDateString();

        $data = array_map(fn ($row) => [
            'product_id' => $row['product_id'],
            'tested'     => $row['tested'],
            'passed'     => $row['passed'],
            'qc_date'    => $date,
            'created_at' => now(),
            'updated_at' => now(),
        ], $rows);

        QcRecord::upsert(
            $data,
            ['product_id', 'qc_date'],
            ['tested', 'passed']
        );

        $saved = QcRecord::with('product')
            ->where('qc_date', $date)
            ->whereIn('product_id', array_column($rows, 'product_id'))
            ->get();

        $this->realtime->emit(
            RealtimeModule::QC,
            RealtimeAction::BULK_CREATED,
            [
                'count' => $saved->count(),
                'ids'   => $saved->pluck('id')->values(),
            ]
        );

        return $saved;
    }

    /**
     * Latest QC record per product, optionally filtered to a date range.
     */
    public function getLatest(?string $from = null, ?string $to = null): Collection
    {
        return QcRecord::with('product')
            ->latestPerProduct($from, $to)
            ->orderBy('product_id')
            ->get();
    }

    /**
     * Aggregate summary for the matching QC records.
     */
    public function getSummary(?string $from = null, ?string $to = null): array
    {
        $latest = $this->getLatest($from, $to);

        $totalTested = (int) $latest->sum('tested');
        $totalPassed = (int) $latest->sum('passed');
        $totalFailed = $totalTested - $totalPassed;

        return [
            'samples_tested'  => $totalTested,
            'samples_passed'  => $totalPassed,
            'samples_failed'  => $totalFailed,
            'pass_rate'       => $totalTested > 0
                                    ? round(($totalPassed / $totalTested) * 100, 4)
                                    : 0,
            'rejection_rate'  => $totalTested > 0
                                    ? round(($totalFailed / $totalTested) * 100, 4)
                                    : 0,
            'products_tested' => $latest->count(),
            'from'            => $from,
            'to'              => $to,
        ];
    }
}