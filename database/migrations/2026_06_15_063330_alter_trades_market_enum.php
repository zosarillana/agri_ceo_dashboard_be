<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL doesn't support ALTER COLUMN on enums directly,
        // so we use a raw query to modify it
        DB::statement("ALTER TABLE trades MODIFY COLUMN market ENUM('Export', 'Local', 'CWC') NOT NULL DEFAULT 'Export'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE trades MODIFY COLUMN market ENUM('Export', 'Local') NOT NULL DEFAULT 'Export'");
    }
};