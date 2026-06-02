<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('department');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('department', [
                'production',
                'procurement',
                'sales',
                'accounts',
                'trading',
                'quality_control',
                'workforce',
                'maintenance',
                'energy',
            ])->nullable();
        });
    }
};
