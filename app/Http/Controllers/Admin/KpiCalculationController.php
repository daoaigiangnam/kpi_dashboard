<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobTitle;
use App\Models\KpiCalculationRun;
use App\Models\KpiSlaPriority;
use App\Models\KpiWeight;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KpiCalculationController extends Controller
{
    public function calculate(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:10'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $employee = User::query()->with('jobTitle')->where('is_active', true)->findOrFail($data['employee_id']);
        $search = trim((string) ($data['search'] ?? ''));
        $priority = strtoupper(trim((string) ($data['priority'] ?? '')));
        $dateFrom = $data['date_from'] ?? null;
        $dateTo = $data['date_to'] ?? null;

        $query = Ticket::query()
            ->where('employee_id', $employee->id)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('external_ticket_id', 'like', "%{$search}%")
                        ->orWhere('company_department', 'like', "%{$search}%")
                        ->orWhere('resolution_detail', 'like', "%{$search}%");
                });
            })
            ->when($priority !== '', fn ($q) => $q->where('priority', $priority))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_on', '<=', $dateTo));

        $tickets = $query->get();
        $completed = $tickets->filter(fn ($ticket) => $ticket->finished_on !== null);
        $totalTickets = $tickets->count();
        $completedTickets = $completed->count();
        $workloadPointCompleted = (float) $completed->sum(fn ($ticket) => (float) ($ticket->workload_point ?? 0));

        $targetWorkloadPoint = (float) ($employee->jobTitle?->target_workload_point ?? 0);
        $weights = KpiWeight::orderBy('sort_order')->get()->keyBy('code');
        $weight = fn (string $code): float => (float) ($weights[$code]->weight ?? 0);

        // 1. PRODUCTIVITY
        $k = $targetWorkloadPoint > 0
            ? ($workloadPointCompleted / $targetWorkloadPoint) * 100
            : 0;
        $p = ($k * $weight('productivity')) / 100;

        // 2. SLA COMPLIANCE - priority-weighted completed tickets.
        $priorityConfigs = KpiSlaPriority::orderBy('sort_order')->get()->keyBy(fn ($item) => strtoupper($item->code));
        $slaRows = [];
        $weightedTotal = 0;
        $weightedMet = 0;
        foreach ($priorityConfigs as $code => $config) {
            $priorityTickets = $completed->filter(fn ($ticket) => strtoupper((string) $ticket->priority) === $code);
            $count = $priorityTickets->count();
            $met = $priorityTickets->filter(fn ($ticket) => $ticket->sla_status === 'Đạt')->count();
            $weightedTotal += $count * (int) $config->weight;
            $weightedMet += $met * (int) $config->weight;
            $slaRows[] = [
                'priority' => $code,
                'total' => $count,
                'met' => $met,
                'weight' => (int) $config->weight,
            ];
        }
        $slaCompliance = $weightedTotal > 0 ? ($weightedMet / $weightedTotal) * 100 : 0;
        $slaKpi = ($slaCompliance * $weight('sla_compliance')) / 100;

        // 3. QUALITY (REOPEN)
        $reopenedTickets = $completed->filter(fn ($ticket) => (int) $ticket->reopen_count > 0)->count();
        $reopenRate = $completedTickets > 0 ? ($reopenedTickets / $completedTickets) * 100 : 0;
        $quality = 100 - $reopenRate;
        $q = ($quality * $weight('quality_reopen')) / 100;

        // 4. PROCESS COMPLIANCE
        $processMet = $completed->filter(fn ($ticket) => $ticket->process_status === 'Đạt')->count();
        $processCompliance = $completedTickets > 0 ? ($processMet / $completedTickets) * 100 : 0;
        $ps = ($processCompliance * $weight('process_compliance')) / 100;

        // 5. TICKET RESPONSIVENESS
        $startedTickets = $tickets->filter(fn ($ticket) => $ticket->started_on !== null)->count();
        $responsiveness = $totalTickets > 0 ? ($startedTickets / $totalTickets) * 100 : 0;
        // The KPI weighting follows the same rule as the other four criteria: / 100.
        $res = ($responsiveness * $weight('ticket_responsiveness')) / 100;

        $totalKpi = $p + $slaKpi + $q + $ps + $res;

        $criteria = [
            'search' => $search,
            'priority' => $priority,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'employee_id' => $employee->id,
        ];

        $run = KpiCalculationRun::create([
            'run_code' => 'KPI-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(5)),
            'employee_id' => $employee->id,
            'period_from' => $dateFrom,
            'period_to' => $dateTo,
            'criteria' => $criteria,
            'weights_snapshot' => $weights->mapWithKeys(fn ($item) => [$item->code => ['name' => $item->name, 'weight' => (int) $item->weight]])->all(),
            'metrics' => [
                'target_workload_point' => $targetWorkloadPoint,
                'workload_point_completed' => $workloadPointCompleted,
                'k' => $k,
                'p' => $p,
                'sla' => [
                    'rows' => $slaRows,
                    'weighted_total' => $weightedTotal,
                    'weighted_met' => $weightedMet,
                    'compliance' => $slaCompliance,
                    'kpi' => $slaKpi,
                ],
                'quality' => [
                    'completed_tickets' => $completedTickets,
                    'reopened_tickets' => $reopenedTickets,
                    'reopen_rate' => $reopenRate,
                    'quality' => $quality,
                    'kpi' => $q,
                ],
                'process' => [
                    'completed_tickets' => $completedTickets,
                    'met' => $processMet,
                    'compliance' => $processCompliance,
                    'kpi' => $ps,
                ],
                'responsiveness' => [
                    'total_tickets' => $totalTickets,
                    'started_tickets' => $startedTickets,
                    'percentage' => $responsiveness,
                    'kpi' => $res,
                ],
                'total_kpi' => $totalKpi,
            ],
            'total_kpi' => $totalKpi,
            'calculated_by' => $request->user()?->id,
            'calculated_at' => now(),
        ]);

        return redirect()->route('admin.tickets.index', array_filter([
            'search' => $search,
            'priority' => $priority,
            'employee_id' => $employee->id,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'kpi_run' => $run->id,
        ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', 'KPI calculated and saved as '.$run->run_code.'.');
    }
}
