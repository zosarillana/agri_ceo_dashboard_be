<?php

namespace App\Services;

use App\Enum\RealtimeAction;
use App\Enum\RealtimeModule;
use App\Models\Trade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TradeService
{
    public function __construct(
        private RealtimeService $realtime
    ) {}

    /**
     * Smart bulk save: upsert by (trade_item_id, trade_date).
     */
    public function storeBulk(array $rows, ?string $tradeDate = null): Collection
    {
        $date = $tradeDate
            ? Carbon::parse($tradeDate)->toDateString()
            : Carbon::today()->toDateString();

        $data = array_map(fn ($row) => [
            'trade_item_id' => $row['trade_item_id'],
            'market'        => $row['market'],
            'counterparty'  => $row['counterparty'] ?? null,
            'input_kg'      => $row['input_kg'],
            'output_kg'     => $row['output_kg'],
            'trade_date'    => $date,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $rows);

        Trade::upsert(
            $data,
            ['trade_item_id', 'trade_date'],
            ['market', 'counterparty', 'input_kg', 'output_kg', 'updated_at']
        );

        $saved = Trade::with('tradeItem')
            ->where('trade_date', $date)
            ->whereIn('trade_item_id', array_column($rows, 'trade_item_id'))
            ->get();

        $this->realtime->emit(
            RealtimeModule::TRADE,
            RealtimeAction::BULK_CREATED,
            [
                'count' => $saved->count(),
                'ids'   => $saved->pluck('id')->values(),
            ]
        );

        return $saved;
    }

    /**
     * Get trades in a date range (RAW DATA, not "latest per item").
     * This is what you want for MONTH-TO-DATE totals.
     */
    public function getBetween(?string $from = null, ?string $to = null): Collection
    {
        $query = Trade::with('tradeItem');

        if ($from) {
            $query->whereDate('trade_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('trade_date', '<=', $to);
        }

        return $query->orderBy('trade_date')->get();
    }

    /**
     * Month-to-date helper (THIS is what you asked for).
     */
    public function getMonthToDate(): Collection
    {
        $from = Carbon::now()->startOfMonth()->toDateString();
        $to   = Carbon::today()->toDateString();

        return $this->getBetween($from, $to);
    }

    /**
     * Summary totals for a range (INCLUDING MONTH-TO-DATE).
     */
    public function getSummary(?string $from = null, ?string $to = null): array
    {
        $trades = $this->getBetween($from, $to);

        $totalVolume = $trades->sum('output_kg'); // Changed from quantity_kg to output_kg
        $totalValue  = $trades->sum('total_value');

        return [
            'total_volume'  => (float) $totalVolume,
            'total_value'   => (float) $totalValue,
            'avg_price'     => $totalVolume > 0
                ? (float) ($totalValue / $totalVolume)
                : 0,

            'total_orders'  => $trades->count(),
            'export_orders' => $trades->where('market', 'Export')->count(),
            'local_orders'  => $trades->where('market', 'Local')->count(),

            'from'          => $from,
            'to'            => $to,
        ];
    }

    /**
     * Quick Month-to-Date summary (what you likely want in dashboard).
     */
    public function getMonthToDateSummary(): array
    {
        return $this->getSummary(
            Carbon::now()->startOfMonth()->toDateString(),
            Carbon::today()->toDateString()
        );
    }

    public function deleteTrade(int $id): bool
    {
        $trade   = Trade::findOrFail($id);
        $deleted = $trade->delete();

        $this->realtime->emit(
            RealtimeModule::TRADE,
            RealtimeAction::DELETED,
            ['id' => $id]
        );

        return $deleted;
    }

    public function updateTrade(int $id, array $data): Trade
    {
        $trade = Trade::findOrFail($id);

        $trade->update([
            'market'       => $data['market'],
            'counterparty' => $data['counterparty'] ?? null,
            'input_kg'     => $data['input_kg'],
            'output_kg'    => $data['output_kg'],
        ]);

        $updated = $trade->fresh('tradeItem');

        $this->realtime->emit(
            RealtimeModule::TRADE,
            RealtimeAction::UPDATED,
            ['id' => $updated->id]
        );

        return $updated;
    }
}