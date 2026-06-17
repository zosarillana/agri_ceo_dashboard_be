<?php

namespace Database\Seeders;

use App\Models\Trade;
use App\Models\TradeItem;
use Illuminate\Database\Seeder;

class TradeSeeder extends Seeder
{
    public function run(): void
    {
        // Keyed by trade item code for easy lookup
        $trades = [
            'DC-ONTRADE' => [
                'market'       => 'CWC',
                'counterparty' => 'CWC Buyer',
                'price_per_kg' => 85.00,
                'quantity_kg'  => 12000.00,
                'trade_date'   => '2025-06-01',
            ],
            'FMS-CAKE-VCO' => [
                'market'       => 'Export',
                'counterparty' => 'FMS International',
                'price_per_kg' => 120.50,
                'quantity_kg'  => 8500.00,
                'trade_date'   => '2025-06-01',
            ],
            'FMS-DC-VCO' => [
                'market'       => 'Export',
                'counterparty' => 'FMS International',
                'price_per_kg' => 135.00,
                'quantity_kg'  => 1200.00,
                'trade_date'   => '2025-06-01',
            ],
            'NA-COPRA-RBD' => [
                'market'       => 'Export',
                'counterparty' => 'New Asia Trading',
                'price_per_kg' => 98.75,
                'quantity_kg'  => 6000.00,
                'trade_date'   => '2025-06-01',
            ],
            'LOCAL-SALE' => [
                'market'       => 'Local',
                'counterparty' => null,
                'price_per_kg' => 75.00,
                'quantity_kg'  => 0.00,
                'trade_date'   => '2025-06-01',
            ],
        ];

        foreach ($trades as $code => $data) {
            $tradeItem = TradeItem::where('code', $code)->first();

            if (! $tradeItem) {
                $this->command->warn("TradeItem [{$code}] not found, skipping.");
                continue;
            }

            Trade::updateOrCreate(
                [
                    'trade_item_id' => $tradeItem->id,
                    'trade_date'    => $data['trade_date'],
                ],
                array_merge($data, ['trade_item_id' => $tradeItem->id])
            );
        }
    }
}