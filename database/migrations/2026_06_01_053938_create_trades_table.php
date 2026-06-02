<?php
// database/migrations/2024_01_01_000001_create_trades_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->enum('market', ['Export', 'Local'])->default('Export');
            $table->string('counterparty')->nullable();
            $table->decimal('price_per_kg', 12, 4)->default(0);
            $table->decimal('quantity_kg', 12, 4)->default(0);
            $table->decimal('total_value', 12, 4)->storedAs('price_per_kg * quantity_kg');
            $table->date('trade_date');
            $table->timestamps();

            // Unique constraint to prevent duplicate trades for same product on same date
            $table->unique(['product_id', 'trade_date']);
            
            // Indexes for better query performance
            $table->index('trade_date');
            $table->index('market');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};