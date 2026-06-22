<?php

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
        // First, check if the table exists and drop it
        if (Schema::hasTable('trades')) {
            Schema::dropIfExists('trades');
        }
        
        // Recreate with correct columns
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_item_id')->constrained('trade_items')->onDelete('cascade');
            $table->enum('market', ['Export', 'Local'])->default('Export');
            $table->string('counterparty')->nullable();
            $table->decimal('input_kg', 20, 4)->default(0);
            $table->decimal('output_kg', 20, 4)->default(0);
            $table->decimal('total_value', 20, 4)->storedAs('input_kg * output_kg');
            $table->date('trade_date');
            $table->timestamps();

            // Remove unique constraint to allow multiple trades per item per day
            // $table->unique(['trade_item_id', 'trade_date']);
            
            // Indexes for better query performance
            $table->index('trade_date');
            $table->index('market');
            $table->index(['trade_item_id', 'trade_date']);
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