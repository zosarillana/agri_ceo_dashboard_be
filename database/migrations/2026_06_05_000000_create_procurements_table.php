<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('item_name');
            $table->string('supplier')->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->string('unit')->default('kg');
            $table->enum('status', ['received', 'pending', 'delayed'])->default('pending');
            $table->date('procurement_date');
            $table->timestamps();

            $table->index('procurement_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurements');
    }
};