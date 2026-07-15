<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watched_import_files', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('label')->nullable();
            // The file's mtime as of the last successful check — compared
            // against filemtime() on each scheduler tick to decide whether
            // there's anything new to import.
            $table->timestamp('last_modified_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status')->nullable(); // ok | error | unchanged | missing
            $table->text('last_error')->nullable();
            // Per-sheet results from the most recent sync — same shape as
            // the batch UI's results list, for a lightweight status view.
            $table->json('last_summary')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watched_import_files');
    }
};