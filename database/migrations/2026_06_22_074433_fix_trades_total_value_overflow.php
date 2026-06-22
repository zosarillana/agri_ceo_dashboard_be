<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fixes an overflow that occurs when MySQL evaluates the
     * `total_value` generated column for large input_kg/output_kg
     * values (5+ digits). The original expression let MySQL infer
     * the intermediate precision for `input_kg * output_kg`, which
     * can exceed the engine's internal limit for stored generated
     * columns even though the final DECIMAL(20,4) column itself has
     * plenty of room. Explicit CASTs force a known-safe precision,
     * and the result column is widened to be safe for large products.
     */
    public function up(): void
    {
        // Stored generated columns can't be altered in place on most
        // MySQL/MariaDB versions — drop and recreate instead.
        Schema::table('trades', function (Blueprint $table) {
            $table->dropColumn('total_value');
        });

        DB::statement('
            ALTER TABLE trades
            ADD COLUMN total_value DECIMAL(24,4)
            GENERATED ALWAYS AS (
                CAST(input_kg AS DECIMAL(20,4)) * CAST(output_kg AS DECIMAL(20,4))
            ) STORED
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropColumn('total_value');
        });

        DB::statement('
            ALTER TABLE trades
            ADD COLUMN total_value DECIMAL(20,4)
            GENERATED ALWAYS AS (input_kg * output_kg) STORED
        ');
    }
};