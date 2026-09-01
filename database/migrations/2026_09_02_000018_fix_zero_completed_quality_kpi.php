<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('kpi_calculation_runs')->orderBy('id')->chunkById(100, function ($runs) {
            foreach ($runs as $run) {
                $metrics = json_decode((string) $run->metrics, true);
                if (!is_array($metrics)) {
                    continue;
                }

                $qualityMetrics = $metrics['quality'] ?? null;
                if (!is_array($qualityMetrics)) {
                    continue;
                }

                $completedTickets = (int) ($qualityMetrics['completed_tickets'] ?? 0);
                if ($completedTickets !== 0) {
                    continue;
                }

                // Historical snapshots created before the Quality rule fix could
                // incorrectly award 100% Quality (and 15 KPI points) when there
                // were zero completed Tickets. Correct those stored snapshots.
                $oldQualityKpi = (float) ($qualityMetrics['kpi'] ?? 0);

                $qualityMetrics['reopened_tickets'] = 0;
                $qualityMetrics['reopen_rate'] = 0;
                $qualityMetrics['quality'] = 0;
                $qualityMetrics['kpi'] = 0;
                $metrics['quality'] = $qualityMetrics;

                $oldTotal = (float) $run->total_kpi;
                $newTotal = max(0, $oldTotal - $oldQualityKpi);

                $metrics['total_kpi'] = $newTotal;

                DB::table('kpi_calculation_runs')
                    ->where('id', $run->id)
                    ->update([
                        'metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'total_kpi' => $newTotal,
                    ]);
            }
        });
    }

    public function down(): void
    {
        // This data correction is intentionally not reversed because restoring
        // the previous incorrect Quality score would reintroduce the KPI defect.
    }
};
