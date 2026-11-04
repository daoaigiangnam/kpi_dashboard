@extends('layouts.admin')
@section('title','Alert Policies')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
        <div><strong>Alert Policies</strong><div class="muted">Mỗi policy thuộc một Service Type và có 3 mốc cảnh báo theo % thời gian còn lại.</div></div>
        <a class="btn" href="{{ route('admin.service_alert_policies.create') }}">+ Add Alert Policy</a>
    </div>
    <form method="get" class="actions" style="margin-bottom:16px">
        <input class="input" style="max-width:320px;margin:0" name="search" value="{{ $search }}" placeholder="Search policy name...">
        <select class="input" style="max-width:240px;margin:0" name="service_type_id">
            <option value="">All Service Types</option>
            @foreach($serviceTypes as $type)
                <option value="{{ $type->id }}" @selected((string)$serviceTypeId === (string)$type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <button class="btn gray" type="submit">Search</button>
        @if($search !== '' || $serviceTypeId)<a class="btn gray" href="{{ route('admin.service_alert_policies.index') }}">Clear</a>@endif
        <a class="btn gray" href="{{ route('admin.service_alert_policies.index', ['deleted' => $showDeleted ? null : 1, 'search' => $search, 'service_type_id' => $serviceTypeId]) }}">{{ $showDeleted ? 'Hide Deleted' : 'Show Deleted' }}</a>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Service Type</th><th>Policy</th><th>Alert 1</th><th>Alert 2</th><th>Alert 3</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($policies as $policy)
                <tr>
                    <td><strong>{{ $policy->serviceType?->name ?? '—' }}</strong><div class="muted">{{ $policy->serviceType?->code }}</div></td>
                    <td>{{ $policy->name }}</td>
                    <td>{{ number_format((float)$policy->alert_1_percent, 2) }}%</td>
                    <td>{{ number_format((float)$policy->alert_2_percent, 2) }}%</td>
                    <td>{{ number_format((float)$policy->alert_3_percent, 2) }}%</td>
                    <td>{{ $policy->trashed() ? 'Deleted' : ($policy->is_active ? 'Active' : 'Inactive') }}</td>
                    <td class="actions">
                        @if(!$policy->trashed())
                            <a class="btn gray" href="{{ route('admin.service_alert_policies.edit', $policy) }}">Edit</a>
                            <form method="post" action="{{ route('admin.service_alert_policies.destroy', $policy) }}" onsubmit="return confirm('Delete this alert policy?')">@csrf @method('DELETE')<button class="btn red" type="submit">Delete</button></form>
                        @else
                            <form method="post" action="{{ route('admin.service_alert_policies.restore', $policy->id) }}">@csrf @method('PATCH')<button class="btn" type="submit">Restore</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No alert policies found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $policies->links() }}</div>
</div>
@endsection
