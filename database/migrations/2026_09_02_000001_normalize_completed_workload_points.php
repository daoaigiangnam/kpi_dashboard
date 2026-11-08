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
        // A correlated subquery works on both SQLite (CI) and MySQL (production).
        if (
            Schema::hasTable('kpi_sla_priorities')
            && Schema::hasColumn('tickets', 'priority')
            && Schema::hasColumn('tickets', 'finished_on')
            && Schema::hasColumn('tickets', 'workload_point')
            && Schema::hasColumn('kpi_sla_priorities', 'code')
            && Schema::hasColumn('kpi_sla_priorities', 'workload_point')
        ) {
            DB::table('tickets')
                ->whereNotNull('finished_on')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('kpi_sla_priorities as p')
                        ->whereColumn('p.code', 'tickets.priority');
                })
                ->update([
                    'workload_point' => DB::raw(
                        '(SELECT p.workload_point FROM kpi_sla_priorities AS p WHERE p.code = tickets.priority LIMIT 1)'
                    ),
                ]);
        }
    }

    public function down(): void
    {
        // No safe rollback: this migration normalizes derived KPI data.
    }
};
