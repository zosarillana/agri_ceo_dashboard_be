<?php

namespace Database\Seeders;

use App\Models\MaintenanceUnit;
use App\Models\Plant;
use Illuminate\Database\Seeder;

class MaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        $plants = [
            [
                'name' => 'Unit 1',
                'code' => 'U1',
                'units' => [
                    [
                        'name'   => 'PMO',
                        'status' => 'operational',
                        'notes'  => 'Running within normal parameters. No issues reported.',
                        'order'  => 1,
                    ],
                    [
                        'name'   => 'Sheller',
                        'status' => 'operational',
                        'notes'  => 'All belts and bearings checked last shift.',
                        'order'  => 2,
                    ],
                    [
                        'name'   => 'Driller',
                        'status' => 'maintenance',
                        'notes'  => 'Drill head lubrication service in progress.',
                        'order'  => 3,
                    ],
                    [
                        'name'   => 'Dryer',
                        'status' => 'operational',
                        'notes'  => 'Temperature and humidity levels within spec.',
                        'order'  => 4,
                    ],
                    [
                        'name'     => 'Liquid Line',
                        'status'   => 'maintenance',
                        'notes'    => 'Flow rate calibration ongoing across sub-units.',
                        'order'    => 5,
                        'children' => [
                            ['name' => 'Kumar Expeller', 'status' => 'maintenance', 'order' => 1],
                            ['name' => 'CWC',            'status' => 'operational', 'order' => 2],
                            ['name' => 'FPCC',           'status' => 'operational', 'order' => 3],
                        ],
                    ],
                    [
                        'name'   => 'Boiler',
                        'status' => 'operational',
                        'notes'  => 'Steam pressure nominal. Safety valves tested.',
                        'order'  => 6,
                    ],
                ],
            ],
            [
                'name' => 'Unit 2',
                'code' => 'U2',
                'units' => [
                    [
                        'name'   => 'PMO',
                        'status' => 'operational',
                        'notes'  => 'Running within normal parameters.',
                        'order'  => 1,
                    ],
                    [
                        'name'   => 'Sheller',
                        'status' => 'down',
                        'notes'  => 'Drive motor fault detected. Awaiting replacement part.',
                        'order'  => 2,
                    ],
                ],
            ],
            [
                'name' => 'Unit 3',
                'code' => 'U3',
                'units' => [
                    [
                        'name'   => 'Tetra Pack',
                        'status' => 'operational',
                        'notes'  => 'Packaging line running at full capacity.',
                        'order'  => 1,
                    ],
                ],
            ],
        ];

        foreach ($plants as $plantData) {
            $plant = Plant::create([
                'name'      => $plantData['name'],
                'code'      => $plantData['code'],
                'is_active' => true,
            ]);

            foreach ($plantData['units'] as $unitData) {
                $unit = MaintenanceUnit::create([
                    'plant_id'   => $plant->id,
                    'parent_id'  => null,
                    'name'       => $unitData['name'],
                    'status'     => $unitData['status'],
                    'notes'      => $unitData['notes'] ?? null,
                    'sort_order' => $unitData['order'],
                ]);

                foreach ($unitData['children'] ?? [] as $childData) {
                    MaintenanceUnit::create([
                        'plant_id'   => $plant->id,
                        'parent_id'  => $unit->id,
                        'name'       => $childData['name'],
                        'status'     => $childData['status'],
                        'sort_order' => $childData['order'],
                    ]);
                }
            }
        }
    }
}