<?php
// database/migrations/xxxx_xx_xx_add_status_to_accounts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Add the new status column after is_paid
            $table->enum('status', ['received', 'delayed', 'unpaid', 'pending', 'paid'])
                  ->default('unpaid')
                  ->after('is_paid');
        });

        // Migrate existing is_paid data → status
        DB::table('accounts')->where('is_paid', true)->update(['status' => 'paid']);
        DB::table('accounts')->where('is_paid', false)->update(['status' => 'unpaid']);

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('is_paid');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false)->after('notes');
        });

        DB::table('accounts')->where('status', 'paid')->update(['is_paid' => true]);
        DB::table('accounts')->whereIn('status', ['unpaid', 'pending', 'received', 'delayed'])
            ->update(['is_paid' => false]);

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};