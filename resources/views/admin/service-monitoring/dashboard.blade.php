@extends('layouts.admin')
@section('title','IT Monitoring Dashboard')
@section('content')
<div class="grid">
    <div class="card"><div class="muted">Total Services</div><h2>{{ $stats['total'] }}</h2></div>
    <div class="card"><div class="muted">Active</div><h2>{{ $stats['active'] }}</h2></div>
    <div class="card"><div class="muted">Warning</div><h2>{{ $stats['warning'] }}</h2></div>
    <div class="card"><div class="muted">Critical</div><h2>{{ $stats['critical'] + $stats['alert3'] + $stats['expired'] }}</h2></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="actions" style="justify-content:space-between">
        <div><h3 style="margin:0">Monitoring</h3><div class="muted">Evaluate active services against their configured Alert Policy.</div></div>
        <form method="post" action="{{ route('admin.service_monitoring.run') }}">@csrf<button class="btn" type="submit">Run Monitoring Now</button></form>
    </div>
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
    <h3>Open Alerts</h3>
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
            <tr><td colspan="6" class="muted">No open alerts.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
