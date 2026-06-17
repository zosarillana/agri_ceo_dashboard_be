<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();

            // NEW SYSTEM (NO product_id)
            $table->foreignId('trade_item_id')
                ->constrained('trade_items')
                ->cascadeOnDelete();

            $table->enum('market', ['Export', 'Local'])->default('Export');
            $table->string('counterparty')->nullable();

            $table->decimal('price_per_kg', 12, 4)->default(0);
            $table->decimal('quantity_kg', 12, 4)->default(0);

            $table->decimal('total_value', 12, 4)
                ->storedAs('price_per_kg * quantity_kg');

            $table->date('trade_date');

            $table->timestamps();

            $table->unique(['trade_item_id', 'trade_date']);

            $table->index('trade_date');
            $table->index('market');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};