<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_calculation_runs', function (Blueprint $table) {
            $table->string('calculation_key', 120)->nullable()->after('run_code');
            $table->unique('calculation_key');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_calculation_runs', function (Blueprint $table) {
            $table->dropUnique(['calculation_key']);
            $table->dropColumn('calculation_key');
        });
    }
};
