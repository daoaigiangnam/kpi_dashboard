@extends('layouts.admin')
@section('title','IT Service Monitoring Dashboard')
@section('content')
@if($networkStats['offline'] > 0)
<div class="card" style="margin-bottom:16px;border:2px solid #dc2626;background:#fef2f2">
    <div style="font-size:18px;font-weight:700;color:#b91c1c">🔴 FTTH / Internet đang OFFLINE</div>
    <div style="margin-top:6px;color:#7f1d1d">Có <strong>{{ $networkStats['offline'] }}</strong> đường truyền đang mất kết nối. Vui lòng kiểm tra ngay.</div>
</div>
@endif

@if($networkStats['unknown'] > 0)
<div class="card" style="margin-bottom:16px;border:1px solid #f59e0b;background:#fffbeb">
    <div style="font-size:16px;font-weight:700;color:#92400e">🟡 Chưa có kết quả kiểm tra</div>
    <div style="margin-top:6px;color:#78350f">Có <strong>{{ $networkStats['unknown'] }}</strong> dịch vụ đã cấu hình monitoring nhưng chưa được kiểm tra. Hãy bấm <strong>Run Monitoring Now</strong> hoặc kiểm tra Scheduler trên server.</div>
</div>
@endif

<div class="grid">
    <div class="card"><div class="muted">Total Services</div><h2>{{ $stats['total'] }}</h2></div>
    <div class="card"><div class="muted">Active</div><h2>{{ $stats['active'] }}</h2></div>
    <div class="card"><div class="muted">Warning</div><h2>{{ $stats['warning'] }}</h2></div>
    <div class="card"><div class="muted">Critical</div><h2>{{ $stats['critical'] + $stats['alert3'] + $stats['expired'] }}</h2></div>
</div>

<div class="grid" style="margin-top:16px">
    <div class="card"><div class="muted">FTTH / Network Monitors</div><h2>{{ $networkStats['total'] }}</h2></div>
    <div class="card"><div class="muted">ONLINE</div><h2>{{ $networkStats['online'] }}</h2></div>
    <div class="card"><div class="muted">OFFLINE</div><h2>{{ $networkStats['offline'] }}</h2></div>
    <div class="card"><div class="muted">UNKNOWN</div><h2>{{ $networkStats['unknown'] }}</h2></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="actions" style="justify-content:space-between">
        <div><h3 style="margin:0">Service Monitoring</h3><div class="muted">Expiry alerts and continuous network checks for configured Internet/FTTH services.</div></div>
        <form method="post" action="{{ route('admin.service_monitoring.run') }}">@csrf<button class="btn" type="submit">▶ Run Monitoring Now</button></form>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h3>FTTH / Network Status</h3>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Status</th><th>Customer</th><th>Service</th><th>Provider</th><th>Target</th><th>Method</th><th>Port</th><th>Latency</th><th>Loss</th><th>Failures</th><th>Last Check</th></tr></thead>
        <tbody>
        @forelse($monitoredServices as $service)
            <tr style="{{ $service->monitor_status === 'offline' ? 'background:#fef2f2' : '' }}">
                <td><strong>{{ $service->monitor_status === 'offline' ? '🔴 OFFLINE' : ($service->monitor_status === 'online' ? '🟢 ONLINE' : '🟡 UNKNOWN') }}</strong></td>
                <td>{{ $service->customer?->name }}</td>
                <td>{{ $service->service_name }}</td>
                <td>{{ $service->provider?->name ?? '-' }}</td>
                <td>{{ $service->monitor_target }}</td>
                <td>{{ strtoupper($service->monitor_check_method) }}</td>
                <td>{{ $service->monitor_port ?: '-' }}</td>
                <td>{{ $service->monitor_last_latency_ms !== null ? $service->monitor_last_latency_ms.' ms' : '-' }}</td>
                <td>{{ $service->monitor_packet_loss_percent !== null ? $service->monitor_packet_loss_percent.'%' : '-' }}</td>
                <td>{{ $service->monitor_failure_count }}</td>
                <td>{{ optional($service->monitor_last_checked_at)->format('d/m/Y H:i:s') ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="11" class="muted">No network monitors configured.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="card" style="margin-top:16px">
    <h3>Open FTTH Incidents</h3>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Down Since</th><th>Customer</th><th>Service</th><th>Provider</th><th>Target</th><th>Method</th><th>Port</th><th>Reason</th></tr></thead>
        <tbody>
        @forelse($networkIncidents as $incident)
            <tr style="background:#fef2f2">
                <td><strong>{{ optional($incident->started_at)->format('d/m/Y H:i:s') }}</strong></td>
                <td>{{ $incident->service?->customer?->name }}</td>
                <td>{{ $incident->service?->service_name }}</td>
                <td>{{ $incident->service?->provider?->name ?? '-' }}</td>
                <td>{{ $incident->target }}</td>
                <td>{{ strtoupper($incident->check_method) }}</td>
                <td>{{ $incident->port ?: '-' }}</td>
                <td>{{ $incident->error ?: 'Connectivity check failed.' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">No open FTTH incidents.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="card" style="margin-top:16px">
    <h3>Upcoming Expiry</h3>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Customer</th><th>Service</th><th>Type</th><th>Provider</th><th>Expiry</th><th>Alert Stage</th></tr></thead>
        <tbody>
        @forelse($upcoming as $service)
            <tr>
                <td>{{ $service->customer?->name }}</td>
                <td>{{ $service->service_name }}</td>
                <td>{{ $service->serviceType?->name }}</td>
                <td>{{ $service->provider?->name ?? '-' }}</td>
                <td>{{ optional($service->expiry_date)->format('d/m/Y') }}</td>
                <td>{{ $service->alert_stage ?: 'Normal' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No services found.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="card" style="margin-top:16px">
    <h3>Open Expiry Alerts</h3>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Triggered</th><th>Service</th><th>Customer</th><th>Stage</th><th>Remaining</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($openAlerts as $event)
            <tr>
                <td>{{ optional($event->triggered_at)->format('d/m/Y H:i') }}</td>
                <td>{{ $event->service?->service_name }}</td>
                <td>{{ $event->service?->customer?->name }}</td>
                <td>{{ $event->alert_stage == 4 ? 'Expired' : 'Alert '.$event->alert_stage }}</td>
                <td>{{ $event->remaining_percent }}%</td>
                <td>{{ ucfirst($event->status) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No open expiry alerts.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection