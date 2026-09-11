<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_calculation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_code', 40)->unique();
            $table->foreignId('employee_id')->constrained('users');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->json('criteria');
            $table->json('weights_snapshot');
            $table->json('metrics');
            $table->decimal('total_kpi', 8, 4)->default(0);
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->index(['employee_id', 'period_from', 'period_to']);
            $table->index('calculated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_calculation_runs');
    }
};
