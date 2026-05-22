<?php
// database/migrations/xxxx_xx_xx_create_sales_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('market', ['Export', 'Local'])->default('Export');
            $table->decimal('asp_per_kg', 10, 4);
            $table->decimal('quantity_kg', 12, 4);
            $table->decimal('total_sales_usd', 14, 4)->storedAs('asp_per_kg * quantity_kg');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};