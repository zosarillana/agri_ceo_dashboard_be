<?php

namespace App\Services;

use App\Models\TradeItem;
use Illuminate\Support\Collection;

class TradeItemService
{
    public function getAll(): Collection
    {
        return TradeItem::orderBy('name')->get();
    }

    public function findById(int $id): TradeItem
    {
        return TradeItem::findOrFail($id);
    }

    /**
     * Create a new trade item.
     * 
     * @param  array{name: string, code: string, input?: string|null, output?: string|null, market?: string|null}  $data
     * @return TradeItem
     */
    public function create(array $data): TradeItem
    {
        return TradeItem::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'input' => $data['input'] ?? null,
            'output' => $data['output'] ?? null,
            'market' => $data['market'] ?? null,
        ]);
    }

    /**
     * Update an existing trade item.
     * 
     * @param  int  $id
     * @param  array{name: string, code: string, input?: string|null, output?: string|null, market?: string|null}  $data
     * @return TradeItem
     */
    public function update(int $id, array $data): TradeItem
    {
        $item = TradeItem::findOrFail($id);
        $item->update([
            'name' => $data['name'],
            'code' => $data['code'],
            'input' => $data['input'] ?? null,
            'output' => $data['output'] ?? null,
            'market' => $data['market'] ?? null,
        ]);

        return $item->fresh();
    }

    public function delete(int $id): bool
    {
        return TradeItem::findOrFail($id)->delete();
    }

    /**
     * Get trade item with its trades filtered by date range.
     * 
     * Note: The trades will have 'input_kg' and 'output_kg' columns.
     */
    public function getWithTrades(int $id, ?string $from = null, ?string $to = null): TradeItem
    {
        return TradeItem::with(['trades' => function ($query) use ($from, $to) {
            $query
                ->when($from, fn ($q) => $q->whereDate('trade_date', '>=', $from))
                ->when($to,   fn ($q) => $q->whereDate('trade_date', '<=', $to))
                ->orderByDesc('trade_date');
        }])->findOrFail($id);
    }

    /**
     * Check if a trade item code is already taken.
     */
    public function isCodeTaken(string $code, ?int $excludeId = null): bool
    {
        return TradeItem::where('code', $code)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }
}