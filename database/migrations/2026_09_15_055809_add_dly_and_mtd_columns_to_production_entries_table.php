<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_entries', function (Blueprint $table) {
            $table->decimal('dly_target', 12, 2)->default(0)->after('target_output');
            $table->decimal('dly_yield', 12, 2)->default(0)->after('dly_target');
            $table->decimal('mtd_target', 12, 2)->default(0)->after('dly_yield');
            $table->decimal('mtd_yield', 12, 2)->default(0)->after('mtd_target');
        });
    }

    public function down(): void
    {
        Schema::table('production_entries', function (Blueprint $table) {
            $table->dropColumn(['dly_target', 'dly_yield', 'mtd_target', 'mtd_yield']);
        });
    }
};