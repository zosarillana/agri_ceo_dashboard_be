<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')
                  ->constrained('plants')
                  ->cascadeOnDelete();

            // Self-referencing: null = top-level unit, set = sub-unit
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('maintenance_units')
                  ->nullOnDelete();

            $table->string('name');                         // e.g. "PMO", "Liquid Line", "Kumar Expeller"
            $table->enum('status', [
                'operational',
                'maintenance',
                'down',
                'standby',
            ])->default('operational');

            $table->text('notes')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_scheduled_at')->nullable();

            $table->unsignedInteger('sort_order')->default(0); // preserves display order
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_units');
    }
};