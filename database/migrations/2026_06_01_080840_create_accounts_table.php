<?php
// database/migrations/xxxx_xx_xx_create_accounts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->enum('type', ['receivable', 'revenue', 'payable', 'expense', 'capex', 'opex']);
            $table->decimal('amount', 15, 4);
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};