@extends('layouts.admin')

@section('title', 'Tickets')

@section('content')
<style>
    .ticket-toolbar{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}
    .ticket-import{display:grid;grid-template-columns:minmax(240px,1fr) auto auto;gap:10px;align-items:end}
    .ticket-filter{display:grid;grid-template-columns:minmax(220px,1fr) 140px auto;gap:10px;align-items:end;margin-top:16px}
    .ticket-stat{font-size:13px;color:#66736b;margin-top:10px}
    .badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}
    .badge.ok{background:#e8f6ed;color:#24613f}
    .badge.bad{background:#fcebea;color:#8f2f2c}
    .badge.neutral{background:#eef2f7;color:#475569}
    .small{font-size:12px;color:#66736b}
    @media(max-width:800px){
        .ticket-import,.ticket-filter{grid-template-columns:1fr}
    }
</style>

<div class="card">
    <div class="ticket-toolbar">
        <div>
            <h2 style="margin:0 0 4px">Ticket Data</h2>
            <div class="muted">Import raw Ticket data from Bitrix/Excel. KPI calculation fields are generated from the configured KPI Parameters.</div>
        </div>
        <div class="actions">
            <a class="btn gray" href="{{ route('admin.tickets.template') }}">Download Import Template</a>
        </div>
    </div>

    <form method="post" action="{{ route('admin.tickets.import') }}" enctype="multipart/form-data" style="margin-top:18px">
        @csrf
        <div class="ticket-import">
            <div class="field" style="margin:0">
                <label for="ticket-file"><strong>Ticket Excel / CSV</strong></label>
                <input id="ticket-file" class="input" type="file" name="file" accept=".xlsx,.xls,.csv" required>
                <div class="small" style="margin-top:5px">The report <strong>Total</strong> row is ignored and is never stored.</div>
            </div>
            <div>
                <button class="btn" type="submit">Import Tickets</button>
            </div>
        </div>
    </form>

    <form method="get" action="{{ route('admin.tickets.index') }}" class="ticket-filter">
        <div class="field" style="margin:0">
            <label for="ticket-search"><strong>Search</strong></label>
            <input id="ticket-search" class="input" type="text" name="search" value="{{ $search }}" placeholder="Ticket ID, Company/Dept, processing detail">
        </div>
        <div class="field" style="margin:0">
            <label for="ticket-priority"><strong>Priority</strong></label>
            <select id="ticket-priority" class="input" name="priority">
                <option value="">All</option>
                @foreach($priorities as $item)
                    <option value="{{ $item }}" @selected($priority === $item)>{{ $item }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <button class="btn gray" type="submit">Search</button>
        </div>
    </form>

    <div class="ticket-stat">{{ number_format($totalTickets) }} ticket(s) stored. Re-importing the same Ticket ID updates the existing record.</div>

    <div class="table-wrap" style="margin-top:14px">
        <table class="table" style="min-width:1500px">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Priority</th>
                    <th>Created on</th>
                    <th>Started on</th>
                    <th>Finished on</th>
                    <th>Pause (min)</th>
                    <th>Reopen</th>
                    <th>Company/Dept</th>
                    <th>Workload Point</th>
                    <th>Resolution (min)</th>
                    <th>SLA Target</th>
                    <th>SLA</th>
                    <th>Process</th>
                    <th>Started</th>
                    <th>Source</th>
                </tr>
            </thead>
            <tbody>
            @forelse($tickets as $ticket)
                <tr>
                    <td><strong>{{ $ticket->external_ticket_id }}</strong></td>
                    <td>{{ $ticket->priority }}</td>
                    <td>{{ $ticket->created_on?->format('Y-m-d H:i') }}</td>
                    <td>{{ $ticket->started_on?->format('Y-m-d H:i') }}</td>
                    <td>{{ $ticket->finished_on?->format('Y-m-d H:i') }}</td>
                    <td>{{ $ticket->pause_minutes }}</td>
                    <td>{{ $ticket->reopen_count }}</td>
                    <td>{{ $ticket->company_department ?: '—' }}</td>
                    <td>{{ $ticket->workload_point !== null ? number_format((float) $ticket->workload_point, 2) : '—' }}</td>
                    <td>{{ $ticket->resolution_minutes ?? '—' }}</td>
                    <td>{{ $ticket->sla_target_minutes ?? '—' }}</td>
                    <td>
                        @if($ticket->sla_status === 'Đạt')
                            <span class="badge ok">Đạt</span>
                        @elseif($ticket->sla_status === 'Không Đạt')
                            <span class="badge bad">Không Đạt</span>
                        @else
                            <span class="badge neutral">{{ $ticket->sla_status ?: '—' }}</span>
                        @endif
                    </td>
                    <td>
                        @if($ticket->process_status === 'Đạt')
                            <span class="badge ok">Đạt</span>
                        @else
                            <span class="badge bad">{{ $ticket->process_status ?: '—' }}</span>
                        @endif
                    </td>
                    <td><span class="badge neutral">{{ $ticket->started_status }}</span></td>
                    <td class="small">{{ $ticket->source }}</td>
                </tr>
            @empty
                <tr><td colspan="15" style="text-align:center;padding:28px" class="muted">No Ticket data found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">
        {{ $tickets->links() }}
    </div>
</div>
@endsection
