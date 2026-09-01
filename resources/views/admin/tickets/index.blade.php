@extends('layouts.admin')

@section('title', 'Tickets & KPI')

@section('content')
@php
    $selectedKpiRun = request('kpi_run') ? \App\Models\KpiCalculationRun::with('employee')->find(request('kpi_run')) : null;
    $historyEmployeeId = $employeeId ?: ($selectedKpiRun?->employee_id);
    $kpiHistory = $historyEmployeeId
        ? \App\Models\KpiCalculationRun::with('employee')->where('employee_id', $historyEmployeeId)->latest('calculated_at')->limit(10)->get()
        : collect();
@endphp
<style>
    .ticket-toolbar{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}
    .ticket-import{display:grid;grid-template-columns:minmax(240px,1fr) minmax(260px,320px) auto;gap:10px;align-items:end}
    .ticket-filter{display:grid;grid-template-columns:minmax(220px,1fr) 120px 120px 150px minmax(220px,280px) auto;gap:10px;align-items:end;margin-top:16px}
    .ticket-stat{font-size:13px;color:#66736b;margin-top:10px}
    .badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}
    .badge.ok{background:#e8f6ed;color:#24613f}.badge.bad{background:#fcebea;color:#8f2f2c}.badge.neutral{background:#eef2f7;color:#475569}
    .small{font-size:12px;color:#66736b}.import-note{margin-top:10px;padding:10px 12px;background:#f5f8fc;border:1px solid #dbe4ee;border-radius:8px;color:#475569;font-size:12px;line-height:1.45}
    .ticket-total td{font-weight:700;background:#fff7cc;border-top:2px solid #d6b656;white-space:nowrap}
    .ticket-table th,.ticket-table td{vertical-align:middle;white-space:nowrap}
    .ticket-table th:nth-child(9),.ticket-table td:nth-child(9){white-space:normal;min-width:120px}
    .ticket-table th:nth-child(10),.ticket-table td:nth-child(10){white-space:normal;min-width:180px}
    .ticket-table th:nth-child(11),.ticket-table td:nth-child(11){white-space:normal;min-width:180px}
    .kpi-result{margin-top:20px;border:1px solid #cfdbe7;border-radius:10px;background:#fff;overflow:hidden}
    .kpi-result-head{padding:12px 16px;background:#e8f5e9;border-bottom:1px solid #cfdbe7;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
    .kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:10px;padding:14px}
    .kpi-card{border:1px solid #dbe4ee;border-radius:8px;padding:12px;background:#f8fafc}
    .kpi-card .label{font-size:12px;color:#64748b}.kpi-card .value{font-size:22px;font-weight:800;margin-top:5px}.kpi-card.total{background:#fff7cc;border-color:#d6b656}
    .kpi-detail{padding:0 14px 14px}.kpi-detail table th,.kpi-detail table td{font-size:12px}
    .history-title{margin:20px 0 8px;font-size:14px;font-weight:700}.history-table th,.history-table td{white-space:nowrap;font-size:12px}
    @media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(3,minmax(120px,1fr))}.ticket-filter{grid-template-columns:1fr 1fr 1fr}}
    @media(max-width:900px){.ticket-import,.ticket-filter{grid-template-columns:1fr}.kpi-grid{grid-template-columns:1fr 1fr}}
</style>

<div class="card">
    <div class="ticket-toolbar">
        <div>
            <h2 style="margin:0 0 4px">Ticket Data & KPI</h2>
            <div class="muted">Search/Filter is the working dataset. <strong>Tính KPI</strong> creates an auditable KPI calculation run for the selected Employee and period, then keeps the detailed Ticket data below.</div>
        </div>
        <div class="actions">
            <a class="btn gray" href="{{ route('admin.tickets.template') }}">Download Import Template</a>
            <a class="btn gray" href="{{ route('admin.tickets.export', request()->only(['search','priority','employee_id'])) }}">Export Excel</a>
        </div>
    </div>

    <form method="post" action="{{ route('admin.tickets.import') }}" enctype="multipart/form-data" style="margin-top:18px">
        @csrf
        <div class="ticket-import">
            <div class="field" style="margin:0"><label for="ticket-file"><strong>Ticket Excel / CSV</strong></label><input id="ticket-file" class="input" type="file" name="file" accept=".xlsx,.xls,.csv" required><div class="small" style="margin-top:5px">Import only raw input fields. Calculated KPI columns are not required in the file.</div></div>
            <div class="field" style="margin:0"><label for="ticket-employee"><strong>Employee</strong></label><select id="ticket-employee" class="input" name="employee_id" required><option value="">Select employee...</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->name }} — {{ $employee->email }}</option>@endforeach</select><div class="small" style="margin-top:5px">All valid rows in this file will be linked to this Employee ID.</div></div>
            <div><button class="btn" type="submit">Import Tickets</button></div>
        </div>
        <div class="import-note"><strong>Data rule:</strong> Ticket ID is the original Bitrix Ticket ID and must be unique. The Excel Total row is for report checking only and is never stored. Workload Point, Resolution, SLA Target, SLA, Process and Started are calculated by the system, not imported as KPI results.</div>
    </form>

    <form method="get" action="{{ route('admin.tickets.index') }}" class="ticket-filter">
        <div class="field" style="margin:0"><label for="ticket-search"><strong>Search</strong></label><input id="ticket-search" class="input" type="text" name="search" value="{{ $search }}" placeholder="Bitrix Ticket ID, Company/Dept, processing detail"></div>
        <div class="field" style="margin:0"><label for="ticket-priority"><strong>Priority</strong></label><select id="ticket-priority" class="input" name="priority"><option value="">All</option>@foreach($priorities as $item)<option value="{{ $item }}" @selected($priority === $item)>{{ $item }}</option>@endforeach</select></div>
        <div class="field" style="margin:0"><label for="date-from"><strong>From date</strong></label><input id="date-from" class="input" type="date" name="date_from" value="{{ request('date_from') }}"></div>
        <div class="field" style="margin:0"><label for="date-to"><strong>To date</strong></label><input id="date-to" class="input" type="date" name="date_to" value="{{ request('date_to') }}"></div>
        <div class="field" style="margin:0"><label for="ticket-employee-filter"><strong>Employee</strong></label><select id="ticket-employee-filter" class="input" name="employee_id"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) $employeeId === (string) $employee->id)>{{ $employee->name }}</option>@endforeach</select></div>
        <div><button class="btn gray" type="submit">Search</button></div>
    </form>

    <form method="post" action="{{ route('admin.tickets.calculate_kpi') }}" style="margin-top:10px">
        @csrf
        <input type="hidden" name="search" value="{{ $search }}">
        <input type="hidden" name="priority" value="{{ $priority }}">
        <input type="hidden" name="employee_id" value="{{ $employeeId }}">
        <input type="hidden" name="date_from" value="{{ request('date_from') }}">
        <input type="hidden" name="date_to" value="{{ request('date_to') }}">
        <button class="btn" type="submit" @disabled(!$employeeId)>Tính KPI</button>
        @if(!$employeeId)<span class="small" style="margin-left:8px">Select an Employee before calculating KPI.</span>@endif
    </form>

    @if($selectedKpiRun)
        @php($m = $selectedKpiRun->metrics)
        <div class="kpi-result">
            <div class="kpi-result-head">
                <div><strong>Kết quả KPI</strong> — {{ $selectedKpiRun->run_code }} | {{ $selectedKpiRun->employee?->name }} | {{ $selectedKpiRun->period_from?->format('d/m/Y') ?? 'All dates' }} → {{ $selectedKpiRun->period_to?->format('d/m/Y') ?? 'All dates' }}</div>
                <div class="small">Calculated {{ $selectedKpiRun->calculated_at?->format('d/m/Y H:i:s') }}</div>
            </div>
            <div class="kpi-grid">
                <div class="kpi-card"><div class="label">PRODUCTIVITY (P)</div><div class="value">{{ number_format($m['p'] ?? 0, 2) }}</div></div>
                <div class="kpi-card"><div class="label">SLA COMPLIANCE</div><div class="value">{{ number_format($m['sla']['kpi'] ?? 0, 2) }}</div></div>
                <div class="kpi-card"><div class="label">QUALITY (Q)</div><div class="value">{{ number_format($m['quality']['kpi'] ?? 0, 2) }}</div></div>
                <div class="kpi-card"><div class="label">PROCESS COMPLIANCE (PS)</div><div class="value">{{ number_format($m['process']['kpi'] ?? 0, 2) }}</div></div>
                <div class="kpi-card"><div class="label">TICKET RESPONSIVENESS (RES)</div><div class="value">{{ number_format($m['responsiveness']['kpi'] ?? 0, 2) }}</div></div>
                <div class="kpi-card total"><div class="label">TỔNG KPI</div><div class="value">{{ number_format($selectedKpiRun->total_kpi, 2) }}</div></div>
            </div>
            <div class="kpi-detail">
                <table class="table">
                    <thead><tr><th>Chỉ tiêu</th><th>Công thức / Base</th><th>Kết quả %</th><th>Trọng số</th><th>Điểm KPI</th></tr></thead>
                    <tbody>
                        <tr><td>PRODUCTIVITY</td><td>{{ number_format($m['workload_point_completed'] ?? 0, 2) }} WP / {{ number_format($m['target_workload_point'] ?? 0, 2) }} Target</td><td>{{ number_format($m['k'] ?? 0, 2) }}%</td><td>{{ $selectedKpiRun->weights_snapshot['productivity']['weight'] ?? 0 }}%</td><td>{{ number_format($m['p'] ?? 0, 2) }}</td></tr>
                        <tr><td>SLA COMPLIANCE</td><td>{{ $m['sla']['weighted_met'] ?? 0 }} / {{ $m['sla']['weighted_total'] ?? 0 }} weighted tickets</td><td>{{ number_format($m['sla']['compliance'] ?? 0, 2) }}%</td><td>{{ $selectedKpiRun->weights_snapshot['sla_compliance']['weight'] ?? 0 }}%</td><td>{{ number_format($m['sla']['kpi'] ?? 0, 2) }}</td></tr>
                        <tr><td>QUALITY (Q)</td><td>{{ $m['quality']['reopened_tickets'] ?? 0 }} reopened / {{ $m['quality']['completed_tickets'] ?? 0 }} completed</td><td>{{ number_format($m['quality']['quality'] ?? 0, 2) }}%</td><td>{{ $selectedKpiRun->weights_snapshot['quality_reopen']['weight'] ?? 0 }}%</td><td>{{ number_format($m['quality']['kpi'] ?? 0, 2) }}</td></tr>
                        <tr><td>PROCESS COMPLIANCE (PS)</td><td>{{ $m['process']['met'] ?? 0 }} / {{ $m['process']['completed_tickets'] ?? 0 }} completed</td><td>{{ number_format($m['process']['compliance'] ?? 0, 2) }}%</td><td>{{ $selectedKpiRun->weights_snapshot['process_compliance']['weight'] ?? 0 }}%</td><td>{{ number_format($m['process']['kpi'] ?? 0, 2) }}</td></tr>
                        <tr><td>TICKET RESPONSIVENESS (RES)</td><td>{{ $m['responsiveness']['started_tickets'] ?? 0 }} / {{ $m['responsiveness']['total_tickets'] ?? 0 }} tickets</td><td>{{ number_format($m['responsiveness']['percentage'] ?? 0, 2) }}%</td><td>{{ $selectedKpiRun->weights_snapshot['ticket_responsiveness']['weight'] ?? 0 }}%</td><td>{{ number_format($m['responsiveness']['kpi'] ?? 0, 2) }}</td></tr>
                    </tbody>
                </table>
                <div class="small">SLA detail follows the Priority coefficient model: weighted achieved / weighted completed × 100. Any denominator equal to 0 returns 0, never #DIV/0!.</div>
            </div>
        </div>
    @endif

    @if($kpiHistory->count())
        <div class="history-title">Lịch sử các lần Tính KPI — 10 lần gần nhất</div>
        <div class="table-wrap"><table class="table history-table"><thead><tr><th>Run</th><th>Employee</th><th>Kỳ đánh giá</th><th>Calculated at</th><th>P</th><th>SLA</th><th>Q</th><th>PS</th><th>RES</th><th>Tổng KPI</th></tr></thead><tbody>
        @foreach($kpiHistory as $run)
            @php($hm = $run->metrics)
            <tr>
                <td><a href="{{ route('admin.tickets.index', array_merge(request()->only(['search','priority','employee_id','date_from','date_to']), ['kpi_run'=>$run->id])) }}">{{ $run->run_code }}</a></td>
                <td>{{ $run->employee?->name }}</td>
                <td>{{ $run->period_from?->format('d/m/Y') ?? 'All' }} → {{ $run->period_to?->format('d/m/Y') ?? 'All' }}</td>
                <td>{{ $run->calculated_at?->format('d/m/Y H:i') }}</td>
                <td>{{ number_format($hm['p'] ?? 0,2) }}</td><td>{{ number_format($hm['sla']['kpi'] ?? 0,2) }}</td><td>{{ number_format($hm['quality']['kpi'] ?? 0,2) }}</td><td>{{ number_format($hm['process']['kpi'] ?? 0,2) }}</td><td>{{ number_format($hm['responsiveness']['kpi'] ?? 0,2) }}</td><td><strong>{{ number_format($run->total_kpi,2) }}</strong></td>
            </tr>
        @endforeach
        </tbody></table></div>
    @endif

    <div class="ticket-stat">{{ number_format($totalTickets) }} ticket(s) stored. Bitrix Ticket ID is unique; duplicate IDs are skipped during import. The Total row below recalculates from the current search/filter result.</div>
    <div class="table-wrap" style="margin-top:14px"><table class="table ticket-table" style="min-width:2100px"><thead><tr>
        <th>ID</th><th>Priority (Ưu tiên)</th><th>Created on</th><th>Started on</th><th>Finished on</th><th>Pause(min)</th><th>Reopen</th><th>Company/Dept</th><th>Chi tiết nội dung đã xử lý</th><th>File chụp màn hình kết quả xử lý</th><th>Workload Point to Priority</th><th>Resolution (min)</th><th>SLA Target</th><th>SLA</th><th>Process</th><th>Started</th>
    </tr></thead><tbody>
    @forelse($tickets as $ticket)
        <tr>
            <td><strong>{{ $ticket->external_ticket_id }}</strong></td><td>{{ $ticket->priority }}</td><td>{{ $ticket->created_on?->format('n/j/Y G:i') }}</td><td>{{ $ticket->started_on?->format('n/j/Y G:i') }}</td><td>{{ $ticket->finished_on?->format('n/j/Y G:i') }}</td><td>{{ $ticket->pause_minutes }}</td><td>{{ $ticket->reopen_count }}</td><td>{{ $ticket->company_department ?: '' }}</td><td>{{ $ticket->resolution_detail ?: '' }}</td><td>{{ $ticket->result_screenshot ?: '' }}</td><td><strong>{{ $ticket->workload_point !== null ? rtrim(rtrim(number_format((float) $ticket->workload_point, 2, '.', ''), '0'), '.') : '' }}</strong></td><td>{{ $ticket->resolution_minutes ?? '' }}</td><td><strong>{{ $ticket->sla_target_minutes ?? '' }}</strong></td><td>@if($ticket->sla_status === 'Đạt')<span class="badge ok">Đạt</span>@elseif($ticket->sla_status === 'Không Đạt')<span class="badge bad">Không Đạt</span>@else<span class="badge neutral">{{ $ticket->sla_status ?: '' }}</span>@endif</td><td>@if($ticket->process_status === 'Đạt')<span class="badge ok">Đạt</span>@elseif($ticket->process_status === 'Không Đạt')<span class="badge bad">Không Đạt</span>@else<span class="badge neutral">{{ $ticket->process_status ?: '' }}</span>@endif</td><td><span class="badge neutral">{{ $ticket->started_status }}</span></td>
        </tr>
    @empty
        <tr><td colspan="16" style="text-align:center;padding:28px" class="muted">No Ticket data found.</td></tr>
    @endforelse
    <tr class="ticket-total">
        <td>Tổng</td><td>{{ number_format($ticketTotals['ticket_count']) }}</td><td></td><td></td><td>{{ number_format($ticketTotals['finished_count']) }}</td><td>{{ number_format($ticketTotals['pause_minutes']) }}</td><td>{{ number_format($ticketTotals['reopen_ticket_count']) }}</td><td></td><td></td><td></td><td>{{ rtrim(rtrim(number_format($ticketTotals['workload_point'], 2, '.', ''), '0'), '.') }}</td><td></td><td></td><td>{{ number_format($ticketTotals['sla_met']) }}</td><td>{{ number_format($ticketTotals['process_met']) }}</td><td>{{ number_format($ticketTotals['started']) }}</td>
    </tr>
    </tbody></table></div>
    <div style="margin-top:16px">{{ $tickets->links() }}</div>
</div>
@endsection
