@extends('layouts.admin')
@section('title','Users')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:15px">
        <form method="get" action="{{ route('admin.users.index') }}" style="display:flex;gap:8px;flex:1;min-width:280px">
            <input class="input" name="search" value="{{ $search }}" placeholder="Search by employee code, name, email, phone or department" style="margin-top:0">
            <button class="btn" type="submit">Search</button>
            @if($search)<a class="btn gray" href="{{ route('admin.users.index') }}">Clear</a>@endif
        </form>
        <div class="actions">
            @if(auth()->user()->hasPermission('users.import'))<a class="btn gray" href="{{ route('admin.users.template') }}">Download Import Template</a>@endif
            @if(auth()->user()->hasPermission('users.export'))<a class="btn gray" href="{{ route('admin.users.export',request()->only('search')) }}">Export Excel</a>@endif
            @if(auth()->user()->hasPermission('users.import'))
            <form method="post" action="{{ route('admin.users.import') }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center">
                @csrf
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                <button class="btn gray" type="submit">Import Excel</button>
            </form>
            @endif
            @if(auth()->user()->hasPermission('users.create'))<a class="btn" href="{{ route('admin.users.create') }}">+ New User</a>@endif
        </div>
    </div>
    <div class="muted" style="margin-bottom:12px">{{ $users->total() }} user(s) · Employee profile, organization, access group and KPI job title are managed here.</div>
    <div class="table-wrap">
        <table class="table" style="min-width:1100px">
            <thead><tr><th>Employee Code</th><th>Name</th><th>Email</th><th>Department</th><th>Job Title</th><th>Target WP</th><th>Group</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($users as $u)
                <tr>
                    <td><strong>{{ $u->employee_code }}</strong></td><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->department ?? '—' }}</td><td>{{ $u->jobTitle?->name ?? '—' }}</td><td>{{ $u->jobTitle ? number_format((float)$u->jobTitle->target_workload_point,2).' WP' : '—' }}</td><td>{{ $u->group?->name ?? '—' }}</td><td>{{ $u->is_active ? 'Active':'Inactive' }}</td>
                    <td><div class="actions">
                        @if(auth()->user()->hasPermission('users.edit'))<a class="btn gray" href="{{ route('admin.users.edit',$u) }}">Edit</a>@endif
                        @if(auth()->user()->hasPermission('users.delete'))<form method="post" action="{{ route('admin.users.destroy',$u) }}">@csrf @method('DELETE')<button class="btn red" onclick="return confirm('Delete this user?')">Delete</button></form>@endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">No users found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</div>
@endsection
