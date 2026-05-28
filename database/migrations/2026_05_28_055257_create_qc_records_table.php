<?php

// database/migrations/xxxx_xx_xx_create_qc_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('tested');
            $table->unsignedInteger('passed');
            $table->unsignedInteger('failed')->storedAs('tested - passed');
            $table->decimal('pass_rate', 7, 4)->storedAs('(passed / tested) * 100');
            $table->decimal('rejection_rate', 7, 4)->storedAs('((tested - passed) / tested) * 100');
            $table->date('qc_date');
            $table->timestamps();

            $table->unique(['product_id', 'qc_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_records');
    }
};
