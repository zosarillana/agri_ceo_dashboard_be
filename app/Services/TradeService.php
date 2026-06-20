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
     * Add a single trade for a given day (defaults to today).
     * This is just a thin wrapper around store() for convenience/readability
     * when a caller only has one row.
     */
    public function storeSingle(array $row, ?string $tradeDate = null): Trade
    {
        return $this->store($row, $tradeDate);
    }

    /**
     * Insert one trade row. Multiple trades for the same
     * trade_item_id + trade_date are allowed and intentional —
     * your index/dashboard sums them per day, so each call here
     * should create a NEW row rather than overwrite a prior one.
     */
    public function store(array $row, ?string $tradeDate = null): Trade
    {
        $date = $tradeDate
            ? Carbon::parse($tradeDate)->toDateString()
            : Carbon::today()->toDateString();

        $trade = Trade::create([
            'trade_item_id' => $row['trade_item_id'],
            'market'        => $row['market'],
            'counterparty'  => $row['counterparty'] ?? null,
            'input_kg'      => $row['input_kg'],
            'output_kg'     => $row['output_kg'],
            'trade_date'    => $date,
        ]);

        $trade->load('tradeItem');

        $this->realtime->emit(
            RealtimeModule::TRADE,
            RealtimeAction::CREATED,
            ['id' => $trade->id]
        );

        return $trade;
    }

    /**
     * Bulk insert: any number of trades (1 or many) for a given day.
     * Each row becomes its own trade record — no upsert, no overwriting.
     * If multiple rows share the same trade_item_id + trade_date, that's fine;
     * they're separate trades and your summary/index totals will sum them.
     */
    public function storeBulk(array $rows, ?string $tradeDate = null): Collection
    {
        $date = $tradeDate
            ? Carbon::parse($tradeDate)->toDateString()
            : Carbon::today()->toDateString();

        $now = now();

        $data = array_map(fn ($row) => [
            'trade_item_id' => $row['trade_item_id'],
            'market'        => $row['market'],
            'counterparty'  => $row['counterparty'] ?? null,
            'input_kg'      => $row['input_kg'],
            'output_kg'     => $row['output_kg'],
            'trade_date'    => $date,
            'created_at'    => $now,
            'updated_at'    => $now,
        ], $rows);

        // Plain insert — NOT upsert. Each row is a distinct trade,
        // even if it shares trade_item_id + trade_date with another.
        Trade::insert($data);

        // Pull back the rows we just inserted. Since we don't have IDs from
        // insert(), scope by date + item ids + the timestamp we just used,
        // which is unique enough for "what did this call just create."
        $saved = Trade::with('tradeItem')
            ->where('trade_date', $date)
            ->where('created_at', $now)
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
     * Get all trades for a single day. Useful for showing
     * the day's individual entries before they're summed in the index.
     */
    public function getForDate(string $date): Collection
    {
        return Trade::with('tradeItem')
            ->whereDate('trade_date', Carbon::parse($date)->toDateString())
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Summary totals for a range (INCLUDING MONTH-TO-DATE).
     * Trades are summed here per your index's requirement, regardless
     * of how many individual trade rows exist per item per day.
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