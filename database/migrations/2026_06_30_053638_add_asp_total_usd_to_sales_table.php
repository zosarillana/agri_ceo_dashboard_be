<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_asp_total_usd_to_sales_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add the raw "sales" input column if it doesn't already exist.
        // Make it NOT NULL with a default value
        if (! Schema::hasColumn('sales', 'sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->decimal('sales', 14, 4)->default(0)->after('market');
            });
        } else {
            // If column exists, modify it to be NOT NULL with default
            Schema::table('sales', function (Blueprint $table) {
                $table->decimal('sales', 14, 4)->default(0)->change();
            });
        }

        // Make asp_per_kg nullable
        if (Schema::hasColumn('sales', 'asp_per_kg')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->decimal('asp_per_kg', 14, 4)->nullable()->change();
            });
        }

        // Add or modify the stored generated column: asp_per_kg / quantity_kg
        // Handle NULL values in asp_per_kg
        if (! Schema::hasColumn('sales', 'asp_total_usd')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->decimal('asp_total_usd', 14, 4)
                    ->storedAs('CASE WHEN asp_per_kg IS NOT NULL AND quantity_kg IS NOT NULL AND quantity_kg != 0 THEN asp_per_kg / quantity_kg ELSE NULL END')
                    ->after('asp_per_kg');
            });
        } else {
            // If column exists, modify the stored expression to handle NULL
            Schema::table('sales', function (Blueprint $table) {
                $table->decimal('asp_total_usd', 14, 4)
                    ->storedAs('CASE WHEN asp_per_kg IS NOT NULL AND quantity_kg IS NOT NULL AND quantity_kg != 0 THEN asp_per_kg / quantity_kg ELSE NULL END')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'asp_total_usd')) {
                $table->dropColumn('asp_total_usd');
            }
        });

        // Rollback asp_per_kg to NOT NULL
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'asp_per_kg')) {
                $table->decimal('asp_per_kg', 14, 4)->default(0)->change();
            }
        });

        // Rollback sales to nullable (or keep as is)
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'sales')) {
                $table->decimal('sales', 14, 4)->nullable()->change();
            }
        });
    }
};