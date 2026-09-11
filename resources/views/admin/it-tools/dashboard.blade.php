@extends('layouts.admin')
@section('title','IT Tools Dashboard')
@section('content')
<div class="card">
    <h2>IT Outsourcing Tools Dashboard</h2>
    <p class="muted">Audit summary for the last {{ $days }} days.</p>
</div>
<div class="grid" style="margin-top:16px">
    <div class="card"><div class="muted">Audits</div><h2>{{ $total }}</h2></div>
    <div class="card"><div class="muted">Domain ≤ 30 days</div><h2>{{ $expiredDomains }}</h2></div>
    <div class="card"><div class="muted">SSL ≤ 30 days</div><h2>{{ $expiringSsl }}</h2></div>
    <div class="card"><div class="muted">Website Offline</div><h2>{{ $offline }}</h2></div>
    <div class="card"><div class="muted">Failed Audits</div><h2>{{ $failed }}</h2></div>
</div>
<div class="card" style="margin-top:20px">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
        <h3>Recent Audits</h3>
        <a class="button" href="{{ route('admin.it_tools.index') }}">Open Tools</a>
    </div>
    <div style="overflow:auto">
        <table>
            <thead><tr><th>Checked</th><th>Domain</th><th>Status</th><th>Domain Days</th><th>SSL Days</th><th>HTTPS</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($recent as $audit)
                <tr>
                    <td>{{ optional($audit->created_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $audit->domain }}</td>
                    <td>{{ $audit->status }}</td>
                    <td>{{ data_get($audit->result,'domain_audit.days_remaining','—') }}</td>
                    <td>{{ data_get($audit->result,'ssl_audit.days_remaining','—') }}</td>
                    <td>{{ data_get($audit->result,'website_audit.https.status','—') }}</td>
                    <td>{{ data_get($audit->result,'ip_audit.ip','—') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No audit data yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
