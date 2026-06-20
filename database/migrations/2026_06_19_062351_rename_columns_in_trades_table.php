<?php
// database/migrations/2024_01_01_000002_rename_columns_in_trades_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, drop the stored column that depends on the columns being renamed
        Schema::table('trades', function (Blueprint $table) {
            $table->dropColumn('total_value');
        });

        // Rename the columns
        Schema::table('trades', function (Blueprint $table) {
            $table->renameColumn('price_per_kg', 'input_kg');
            $table->renameColumn('quantity_kg', 'output_kg');
        });

        // Recreate the stored column with the new calculation
        Schema::table('trades', function (Blueprint $table) {
            $table->decimal('total_value', 12, 4)->storedAs('input_kg * output_kg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the stored column
        Schema::table('trades', function (Blueprint $table) {
            $table->dropColumn('total_value');
        });

        // Rename back to original names
        Schema::table('trades', function (Blueprint $table) {
            $table->renameColumn('input_kg', 'price_per_kg');
            $table->renameColumn('output_kg', 'quantity_kg');
        });

        // Recreate the stored column with the original calculation
        Schema::table('trades', function (Blueprint $table) {
            $table->decimal('total_value', 12, 4)->storedAs('price_per_kg * quantity_kg');
        });
    }
};