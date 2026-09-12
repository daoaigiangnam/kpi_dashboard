@extends('layouts.admin')
@section('title','Service Catalog')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
        <div><strong>Service Types</strong><div class="muted">Quản lý loại dịch vụ và các chu kỳ được phép sử dụng.</div></div>
        <a class="btn" href="{{ route('admin.service_types.create') }}">+ Add Service Type</a>
    </div>
    <form method="get" class="actions" style="margin-bottom:16px">
        <input class="input" style="max-width:360px;margin:0" name="search" value="{{ $search }}" placeholder="Search code, name...">
        <button class="btn gray" type="submit">Search</button>
        @if($search !== '')<a class="btn gray" href="{{ route('admin.service_types.index') }}">Clear</a>@endif
        <a class="btn gray" href="{{ route('admin.service_types.index', ['deleted' => $showDeleted ? null : 1, 'search' => $search]) }}">{{ $showDeleted ? 'Hide Deleted' : 'Show Deleted' }}</a>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Code</th><th>Service Type</th><th>Allowed Terms</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($serviceTypes as $serviceType)
                <tr>
                    <td><strong>{{ $serviceType->code }}</strong></td>
                    <td>{{ $serviceType->name }}<div class="muted">{{ $serviceType->description }}</div></td>
                    <td>{{ $serviceType->terms->map(fn($term) => $term->months . ' tháng')->implode(', ') }}</td>
                    <td>{{ $serviceType->trashed() ? 'Deleted' : ($serviceType->is_active ? 'Active' : 'Inactive') }}</td>
                    <td class="actions">
                        @if(!$serviceType->trashed())
                            <a class="btn gray" href="{{ route('admin.service_types.edit', $serviceType) }}">Edit</a>
                            <form method="post" action="{{ route('admin.service_types.destroy', $serviceType) }}" onsubmit="return confirm('Delete this service type?')">@csrf @method('DELETE')<button class="btn red" type="submit">Delete</button></form>
                        @else
                            <form method="post" action="{{ route('admin.service_types.restore', $serviceType->id) }}">@csrf @method('PATCH')<button class="btn" type="submit">Restore</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No service types found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $serviceTypes->links() }}</div>
</div>
@endsection
