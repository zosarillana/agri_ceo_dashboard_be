<?php

// app/Services/EnergyService.php

namespace App\Services;

use App\Enum\RealtimeAction;
use App\Enum\RealtimeModule;
use App\Models\EnergyRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EnergyService
{
    public function __construct(
        private RealtimeService $realtime
    ) {}

    /**
     * Smart bulk save:
     * upsert by (account, billing_month)
     *
     * @param array<int, array{
     *     account: string,
     *     month: string,
     *     kw: float,
     *     demand: float,
     *     billedAmount: float
     * }> $rows
     */
    public function storeBulk(array $rows): Collection
    {
        return DB::transaction(function () use ($rows) {

            $saved = collect();

            foreach ($rows as $row) {

                $billingMonth = Carbon::parse($row['month'])
                    ->startOfMonth()
                    ->toDateString();

                $existing = EnergyRecord::where('account', $row['account'])
                    ->whereDate('billing_month', $billingMonth)
                    ->first();

                if ($existing) {

                    $existing->update([
                        'kw' => $row['kw'],
                        'demand' => $row['demand'],
                        'billed_amount' => $row['billedAmount'],
                    ]);

                    $saved->push($existing->fresh());

                } else {

                    $saved->push(
                        EnergyRecord::create([
                            'account' => $row['account'],
                            'billing_month' => $billingMonth,
                            'kw' => $row['kw'],
                            'demand' => $row['demand'],
                            'billed_amount' => $row['billedAmount'],
                        ])
                    );
                }
            }

            $this->realtime->emit(
                RealtimeModule::ENERGY,
                RealtimeAction::BULK_CREATED,
                [
                    'count' => $saved->count(),
                    'ids' => $saved->pluck('id')->values(),
                ]
            );

            return $saved;
        });
    }

    /**
     * Get all energy records grouped by account.
     */
    public function getAll(): array
    {
        $records = EnergyRecord::grouped()->get();

        return [
            'account2' => $records
                ->where('account', 'account2')
                ->values(),

            'account3' => $records
                ->where('account', 'account3')
                ->values(),
        ];
    }

    /**
     * Get records for a specific month.
     */
    public function getByMonth(string $month): array
    {
        $billingMonth = Carbon::parse($month)
            ->startOfMonth()
            ->toDateString();

        $records = EnergyRecord::whereDate('billing_month', $billingMonth)
            ->orderBy('account')
            ->get();

        return [
            'account2' => $records
                ->where('account', 'account2')
                ->values(),

            'account3' => $records
                ->where('account', 'account3')
                ->values(),
        ];
    }

    /**
     * Dashboard summary totals.
     */
    public function getSummary(): array
    {
        $records = EnergyRecord::all();

        return [
            'total_billed_amount' => (float) $records->sum('billed_amount'),
            'total_kw' => (float) $records->sum('kw'),
            'total_demand' => (float) $records->sum('demand'),

            'account2_total' => (float) $records
                ->where('account', 'account2')
                ->sum('billed_amount'),

            'account3_total' => (float) $records
                ->where('account', 'account3')
                ->sum('billed_amount'),
        ];
    }
}
