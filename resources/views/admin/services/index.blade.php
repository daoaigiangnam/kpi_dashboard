@extends('layouts.admin')
@section('title','Services')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
        <div><strong>IT Services</strong><div class="muted">Quản lý các dịch vụ cần theo dõi hạn và cảnh báo.</div></div>
        <a class="btn" href="{{ route('admin.services.create') }}">+ Add Service</a>
    </div>
    <form method="get" class="actions" style="margin-bottom:16px">
        <input class="input" style="max-width:320px;margin:0" name="search" value="{{ $search }}" placeholder="Search service / value...">
        <select class="input" style="max-width:180px;margin:0" name="status">
            <option value="">All Status</option>
            <option value="active" @selected($status==='active')>Active</option>
            <option value="suspended" @selected($status==='suspended')>Suspended</option>
            <option value="expired" @selected($status==='expired')>Expired</option>
        </select>
        <button class="btn gray" type="submit">Filter</button>
        @if($search!==''||$status!=='')<a class="btn gray" href="{{ route('admin.services.index') }}">Clear</a>@endif
        <a class="btn gray" href="{{ route('admin.services.index',['deleted'=>$showDeleted?null:1,'search'=>$search,'status'=>$status]) }}">{{ $showDeleted?'Hide Deleted':'Show Deleted' }}</a>
    </form>
    <div class="table-wrap">
        <table class="table" style="min-width:1200px">
            <thead><tr><th>Customer</th><th>Service</th><th>Type</th><th>Provider</th><th>Term</th><th>Expiry</th><th>Alert Policy</th><th>Responsible IT</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($services as $service)
                <tr>
                    <td>{{ $service->customer?->name }}</td>
                    <td><strong>{{ $service->service_name }}</strong><div class="muted">{{ $service->value }}</div></td>
                    <td>{{ $service->serviceType?->name }}</td>
                    <td>{{ $service->provider?->name }}</td>
                    <td>{{ $service->service_term_months }} tháng</td>
                    <td>{{ optional($service->expiry_date)->format('d/m/Y') }}</td>
                    <td>{{ $service->alertPolicy?->name }}</td>
                    <td>{{ $service->responsibleIt?->name }}</td>
                    <td>{{ ucfirst($service->trashed()?'Deleted':$service->status) }}</td>
                    <td class="actions">
                        @if(!$service->trashed())
                            <a class="btn gray" href="{{ route('admin.services.edit',$service) }}">Edit</a>
                            <form method="post" action="{{ route('admin.services.destroy',$service) }}" onsubmit="return confirm('Delete this service?')">@csrf @method('DELETE')<button class="btn red" type="submit">Delete</button></form>
                        @else
                            <form method="post" action="{{ route('admin.services.restore',$service->id) }}">@csrf @method('PATCH')<button class="btn" type="submit">Restore</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="muted">No services found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $services->links() }}</div>
</div>
@endsection
