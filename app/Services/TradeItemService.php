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
     * @param  array{name: string, code: string, input?: string|null, output?: string|null, market?: string|null}  $data
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
     * @param  array{name: string, code: string, input?: string|null, output?: string|null, market?: string|null}  $data
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

    public function getWithTrades(int $id, ?string $from = null, ?string $to = null): TradeItem
    {
        return TradeItem::with(['trades' => function ($query) use ($from, $to) {
            $query
                ->when($from, fn ($q) => $q->whereDate('trade_date', '>=', $from))
                ->when($to,   fn ($q) => $q->whereDate('trade_date', '<=', $to))
                ->orderByDesc('trade_date');
        }])->findOrFail($id);
    }

    public function isCodeTaken(string $code, ?int $excludeId = null): bool
    {
        return TradeItem::where('code', $code)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }
}