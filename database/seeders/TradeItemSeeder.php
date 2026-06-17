<?php

namespace Database\Seeders;

use App\Models\TradeItem;
use Illuminate\Database\Seeder;

class TradeItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'name'   => 'DC on-trade',
                'code'   => 'DC-ONTRADE',
                'input'  => 'Desiccated coconut',
                'output' => 'Packed DC',
                'market' => 'CWC',
            ],
            [
                'name'   => 'FMS tolling — Cake → VCO',
                'code'   => 'FMS-CAKE-VCO',
                'input'  => 'Cake',
                'output' => 'VCO',
                'market' => 'Export',
            ],
            [
                'name'   => 'FMS tolling — DC → VCO',
                'code'   => 'FMS-DC-VCO',
                'input'  => 'Desiccated coconut',
                'output' => 'VCO',
                'market' => 'Export',
            ],
            [
                'name'   => 'New Asia — copra → RBD',
                'code'   => 'NA-COPRA-RBD',
                'input'  => 'Copra',
                'output' => 'RBD oil',
                'market' => 'Export',
            ],
            [
                'name'   => 'Local sale',
                'code'   => 'LOCAL-SALE',
                'input'  => 'Mixed products',
                'output' => 'Revenue',
                'market' => 'Local',
            ],
        ];

        foreach ($items as $item) {
            TradeItem::updateOrCreate(
                ['code' => $item['code']],
                $item
            );
        }
    }
}