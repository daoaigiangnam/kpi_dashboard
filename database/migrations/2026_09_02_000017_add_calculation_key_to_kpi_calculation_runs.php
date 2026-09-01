<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_calculation_runs', function (Blueprint $table) {
            $table->string('calculation_key', 120)->nullable()->after('run_code');
        });

        // Existing runs are legacy calculation records. Keep them addressable
        // by their own run_code and only enforce the new unique key for future runs.
        // This avoids silently merging historical calculations.
        Schema::table('kpi_calculation_runs', function (Blueprint $table) {
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
