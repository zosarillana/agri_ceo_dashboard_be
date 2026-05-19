<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('maintenance_unit_id')
                  ->constrained('maintenance_units')
                  ->cascadeOnDelete();

            // The user who performed/submitted this check
            $table->foreignId('checked_by')
                  ->constrained('users')
                  ->restrictOnDelete();

            // Status the user observed during this check
            $table->enum('status', [
                'operational',
                'maintenance',
                'down',
                'standby',
            ]);

            // Free-text observation notes for this specific check
            $table->text('notes')->nullable();

            // When the physical check was actually performed
            $table->timestamp('checked_at');

            // When the next check is due (user can update per log entry)
            $table->timestamp('next_scheduled_at')->nullable();

            // Optional: how long the check took in minutes
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
    }
};