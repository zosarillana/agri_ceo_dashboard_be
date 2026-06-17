<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_items', function (Blueprint $table) {
            $table->string('input')->nullable()->after('code');
            $table->string('output')->nullable()->after('input');
            $table->enum('market', ['Export', 'Local', 'CWC'])->nullable()->after('output');
        });
    }

    public function down(): void
    {
        Schema::table('trade_items', function (Blueprint $table) {
            $table->dropColumn(['input', 'output', 'market']);
        });
    }
};