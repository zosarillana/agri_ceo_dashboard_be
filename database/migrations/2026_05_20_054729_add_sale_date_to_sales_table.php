<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_sale_date_to_sales_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Add the sale_date column after quantity_kg
            $table->date('sale_date')->nullable()->after('quantity_kg');
            
            // Add an index for better performance
            $table->index('sale_date');
        });

        // Update existing records to use created_at as sale_date
        DB::table('sales')->update(['sale_date' => DB::raw('DATE(created_at)')]);

        // Now make it not nullable after populating existing data
        Schema::table('sales', function (Blueprint $table) {
            $table->date('sale_date')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('sale_date');
        });
    }
};