@extends('layouts.admin')
@section('title','IT Monitoring - Providers')
@section('content')
<div class="actions" style="justify-content:space-between;margin-bottom:16px">
    <div><h2 style="margin:0">Providers</h2><div class="muted">Manage hosting, cloud, registrar and other service providers.</div></div>
    <a class="btn" href="{{ route('admin.service_providers.create') }}">+ New Provider</a>
</div>

<div class="card">
    <form method="get" class="actions" style="margin-bottom:12px">
        <input name="search" value="{{ $search }}" placeholder="Search code or name">
        <label><input type="checkbox" name="deleted" value="1" @checked($showDeleted)> Show deleted</label>
        <button class="btn" type="submit">Search</button>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Code</th><th>Name</th><th>Website</th><th>Support Contact</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($providers as $provider)
            <tr>
                <td>{{ $provider->code }}</td>
                <td>{{ $provider->name }}</td>
                <td>{{ $provider->website ?: '-' }}</td>
                <td>{{ $provider->support_contact ?: '-' }}</td>
                <td>{{ $provider->deleted_at ? 'Deleted' : ($provider->is_active ? 'Active' : 'Inactive') }}</td>
                <td>
                    @if($provider->deleted_at)
                        <form method="post" action="{{ route('admin.service_providers.restore',$provider->id) }}" style="display:inline">@csrf @method('PATCH')<button class="btn" type="submit">Restore</button></form>
                    @else
                        <a class="btn" href="{{ route('admin.service_providers.edit',$provider) }}">Edit</a>
                        <form method="post" action="{{ route('admin.service_providers.destroy',$provider) }}" style="display:inline" onsubmit="return confirm('Delete this provider?')">@csrf @method('DELETE')<button class="btn" type="submit">Delete</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No providers found.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div style="margin-top:12px">{{ $providers->links() }}</div>
</div>
@endsection
