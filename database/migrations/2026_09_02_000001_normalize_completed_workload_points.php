<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tickets')) {
            return;
        }

        // Existing unfinished tickets must not contribute workload points.
        DB::table('tickets')
            ->whereNull('finished_on')
            ->update(['workload_point' => 0]);

        // Recalculate completed tickets from the current Priority catalog.
        if (Schema::hasTable('kpi_sla_priorities')) {
            DB::table('tickets as t')
                ->join('kpi_sla_priorities as p', function ($join) {
                    $join->on('p.code', '=', 't.priority');
                })
                ->whereNotNull('t.finished_on')
                ->update(['t.workload_point' => DB::raw('p.workload_point')]);
        }
    }

    public function down(): void
    {
        // No safe rollback: this migration normalizes derived KPI data.
    }
};
