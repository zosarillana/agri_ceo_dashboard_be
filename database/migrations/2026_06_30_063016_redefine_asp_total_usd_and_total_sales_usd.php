<?php
// database/migrations/xxxx_xx_xx_xxxxxx_redefine_asp_total_usd_and_total_sales_usd.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Redefine asp_total_usd: sales / quantity_kg (was asp_per_kg / quantity_kg)
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('asp_total_usd', 14, 4)
                ->storedAs('CASE WHEN sales IS NOT NULL AND quantity_kg IS NOT NULL AND quantity_kg != 0 THEN sales / quantity_kg ELSE NULL END')
                ->change();
        });

        // Redefine total_sales_usd: direct copy of sales (was asp_per_kg * quantity_kg).
        // A generated column can only reference columns in the same row, so this
        // mirrors `sales` 1:1 — SUM(total_sales_usd) at the aggregate level then
        // equals SUM(sales), which is what "total sales = sum of sales" means.
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('total_sales_usd', 14, 4)
                ->storedAs('sales')
                ->change();
        });
    }

    public function down(): void
    {
        // Revert total_sales_usd to asp_per_kg * quantity_kg
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('total_sales_usd', 14, 4)
                ->storedAs('asp_per_kg * quantity_kg')
                ->change();
        });

        // Revert asp_total_usd to asp_per_kg / quantity_kg
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('asp_total_usd', 14, 4)
                ->storedAs('CASE WHEN asp_per_kg IS NOT NULL AND quantity_kg IS NOT NULL AND quantity_kg != 0 THEN asp_per_kg / quantity_kg ELSE NULL END')
                ->change();
        });
    }
};