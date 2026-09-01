<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KpiSlaPriority;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;

class TicketKpiPageController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $priority = strtoupper(trim((string) $request->query('priority', '')));
        $employeeId = $request->query('employee_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $baseQuery = $this->ticketQuery($search, $priority, $employeeId, $dateFrom, $dateTo);
        $ticketTotals = $this->ticketTotals(clone $baseQuery);
        $tickets = $baseQuery->paginate(25)->withQueryString();
        $priorities = KpiSlaPriority::orderBy('sort_order')->pluck('code');
        $employees = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);
        $totalTickets = Ticket::count();

        return view('admin.tickets.index', compact(
            'tickets', 'search', 'priority', 'employeeId', 'dateFrom', 'dateTo',
            'priorities', 'employees', 'totalTickets', 'ticketTotals'
        ));
    }

    private function ticketQuery(string $search, string $priority, mixed $employeeId, ?string $dateFrom, ?string $dateTo)
    {
        return Ticket::query()->with('employee')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('external_ticket_id', 'like', "%{$search}%")
                        ->orWhere('company_department', 'like', "%{$search}%")
                        ->orWhere('resolution_detail', 'like', "%{$search}%");
                });
            })
            ->when($priority !== '', fn ($q) => $q->where('priority', $priority))
            ->when($employeeId !== null && $employeeId !== '', fn ($q) => $q->where('employee_id', (int) $employeeId))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_on', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_on', '<=', $dateTo))
            ->orderByRaw('CAST(external_ticket_id AS UNSIGNED) ASC')
            ->orderBy('external_ticket_id');
    }

    private function ticketTotals($query): array
    {
        $completed = (clone $query)->whereNotNull('finished_on');

        return [
            'ticket_count' => (clone $query)->count(),
            'created_count' => (clone $query)->whereNotNull('created_on')->count(),
            'started_count' => (clone $query)->whereNotNull('started_on')->count(),
            'finished_count' => (clone $query)->whereNotNull('finished_on')->count(),
            'pause_minutes' => (int) ((clone $query)->sum('pause_minutes') ?? 0),
            'reopen_ticket_count' => (clone $query)->where('reopen_count', '>', 0)->count(),
            'company_department_count' => (clone $query)->whereNotNull('company_department')->where('company_department', '<>', '')->count(),
            'resolution_detail_count' => (clone $query)->whereNotNull('resolution_detail')->where('resolution_detail', '<>', '')->count(),
            'result_screenshot_count' => (clone $query)->whereNotNull('result_screenshot')->where('result_screenshot', '<>', '')->count(),
            'workload_point' => (float) ((clone $query)->sum('workload_point') ?? 0),
            'resolution_minutes' => (int) ($completed->sum('resolution_minutes') ?? 0),
            'sla_target_minutes' => (int) ($completed->sum('sla_target_minutes') ?? 0),
            'sla_met' => (clone $completed)->where('sla_status', 'Đạt')->count(),
            'sla_not_met' => (clone $completed)->where('sla_status', 'Không Đạt')->count(),
            'process_met' => (clone $completed)->where('process_status', 'Đạt')->count(),
            'started' => (clone $query)->where('started_status', 'Có')->count(),
        ];
    }
}
