<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_template_id')
                ->constrained('import_templates')
                ->cascadeOnDelete();
            $table->string('sheet_name');
            $table->string('source_file')->nullable();
            $table->unsignedInteger('row_number');
            // The decoded, column_map-keyed row data as JSON —
            // see SingleSheetTemplateImport::collection().
            $table->json('data');
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->index(['import_template_id', 'sheet_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};