<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('header_row');
            // Header labels used for auto-detection matching (see
            // TemplateDetector) — stored as a JSON array of strings.
            $table->json('signature');
            // Column letter -> label -> db_column -> role mapping, e.g.
            // [{"letter":"A","label":"Nuts","db_column":"nuts","role":"product_name"}, ...]
            $table->json('column_map');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_templates');
    }
};