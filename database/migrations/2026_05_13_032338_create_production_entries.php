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
        Schema::create('production_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->date('production_date');

            $table->decimal('actual_output', 12, 2)
                ->default(0);

            $table->decimal('target_output', 12, 2)
                ->default(0);

            $table->text('remarks')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'product_id',
                'production_date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_entries');
    }
};
