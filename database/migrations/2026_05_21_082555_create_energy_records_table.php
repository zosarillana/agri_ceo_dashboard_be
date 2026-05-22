<?php
// database/migrations/2026_05_21_000000_create_energy_records_table.php

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
        Schema::create('energy_records', function (Blueprint $table) {
            $table->id();

            $table->enum('account', ['account2', 'account3']);

            // Store as YYYY-MM-01
            $table->date('billing_month');

            $table->decimal('kw', 15, 2)->default(0);
            $table->decimal('demand', 15, 2)->default(0);
            $table->decimal('billed_amount', 15, 2)->default(0);

            $table->timestamps();

            // Prevent duplicate account/month
            $table->unique(['account', 'billing_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('energy_records');
    }
};