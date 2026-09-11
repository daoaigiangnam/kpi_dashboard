@extends('layouts.admin')
@section('title','IT Tools Audit History')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
        <div><h2 style="margin:0">Audit History</h2><div class="muted">Recent domain, SSL, website, email and IP audits.</div></div>
        <div class="actions">
            <a class="btn gray" href="{{ route('admin.it_tools.index') }}">Tools</a>
            <a class="btn" href="{{ route('admin.it_tools.history.export') }}">Export Excel</a>
        </div>
    </div>
    <form method="get" style="margin-top:16px;display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        <div style="min-width:260px"><label>Domain</label><input class="input" name="domain" value="{{ request('domain') }}" placeholder="example.com"></div>
        <button class="btn" type="submit">Filter</button>
        @if(request('domain'))<a class="btn gray" href="{{ route('admin.it_tools.history') }}">Clear</a>@endif
    </form>
</div>
<div class="card" style="margin-top:20px;overflow:auto">
<table class="table">
<thead><tr><th>Checked</th><th>Domain</th><th>Status</th><th>Domain</th><th>SSL</th><th>HTTPS</th><th>IP</th><th>Duration</th></tr></thead>
<tbody>
@forelse($audits as $audit)
@php($d=data_get($audit->result,'domain_audit',[]))
@php($s=data_get($audit->result,'ssl_audit',[]))
@php($w=data_get($audit->result,'website_audit.https',[]))
@php($i=data_get($audit->result,'ip_audit',[]))
<tr>
<td>{{ optional($audit->created_at)->format('Y-m-d H:i:s') }}</td>
<td>{{ $audit->domain }}</td>
<td>{{ $audit->status }}</td>
<td>{{ $d['days_remaining'] ?? '—' }}</td>
<td>{{ $s['days_remaining'] ?? '—' }}</td>
<td>{{ $w['status'] ?? '—' }}</td>
<td>{{ $i['ip'] ?? '—' }}</td>
<td>{{ $audit->duration_ms ?? '—' }} ms</td>
</tr>
@empty
<tr><td colspan="8" class="muted">No audit history found.</td></tr>
@endforelse
</tbody>
</table>
{{ $audits->withQueryString()->links() }}
</div>
@endsection
