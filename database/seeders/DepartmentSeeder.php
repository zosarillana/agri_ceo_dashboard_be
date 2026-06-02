<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'production',
            'procurement',
            'sales',
            'accounts',
            'trading',
            'quality_control',
            'workforce',
            'maintenance',
            'energy',
        ];

        foreach ($departments as $name) {
            DB::table('departments')->updateOrInsert([
                'name' => $name,
            ]);
        }
    }
}