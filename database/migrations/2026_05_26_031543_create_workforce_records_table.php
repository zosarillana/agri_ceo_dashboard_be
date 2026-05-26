<?php

// database/migrations/xxxx_xx_xx_create_workforce_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workforce_records', function (Blueprint $table) {
            $table->id();

            // Department identifier — matches the frontend `key` (e.g. "hr", "prod_dry_process")
            $table->string('department_key');

            // Human-readable label stored for convenience
            $table->string('department_label');

            // Which cost group this row belongs to
            $table->enum('section', ['DEPARTMENT', 'DIRECT COST']);

            // Core metrics
            $table->unsignedSmallInteger('present')->default(0);
            $table->unsignedSmallInteger('headcount')->default(0);
            $table->unsignedSmallInteger('incidents')->default(0);

            // Computed & stored so queries don't need to recalculate
            $table->decimal('attendance_rate', 5, 2)->nullable();

            // One row per department per day
            $table->date('record_date');

            $table->timestamps();

            // Upsert unique constraint
            $table->unique(['department_key', 'record_date'], 'workforce_dept_date_unique');

            // Fast range queries
            $table->index('record_date');
            $table->index('section');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_records');
    }
};