@extends('layouts.admin')
@section('title','Users')
@section('content')
<div class="card">
 <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:15px">
  <div><strong>User Management</strong><div class="muted">Employee profile, organization, access group and KPI job title are managed here.</div></div>
  <div class="actions">
   @if(auth()->user()->isSuperAdmin())<a class="btn" href="{{ route('admin.users.pending') }}">Pending Registrations @if($pendingCount)<span style="display:inline-block;margin-left:4px;padding:2px 7px;border-radius:999px;background:#fff;color:#b45309;font-size:12px">{{ $pendingCount }}</span>@endif</a>@endif
   @if(auth()->user()->hasPermission('users.import'))<a class="btn gray" href="{{ route('admin.users.template') }}">Download Import Template</a>@endif
   @if(auth()->user()->hasPermission('users.export'))<a class="btn gray" href="{{ route('admin.users.export',request()->only('search','deleted')) }}">Export Excel</a>@endif
   @if(auth()->user()->hasPermission('users.import'))<form method="post" action="{{ route('admin.users.import') }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center"><input class="input" type="file" name="file" accept=".xlsx,.xls,.csv" required style="width:auto;margin-top:0">@csrf<button class="btn gray" type="submit">Import Excel</button></form>@endif
   @if(auth()->user()->hasPermission('users.create') && !$showDeleted)<a class="btn" href="{{ route('admin.users.create') }}">+ New User</a>@endif
  </div>
 </div>
 <form method="get" action="{{ route('admin.users.index') }}" style="display:flex;gap:8px;margin-bottom:15px;max-width:1100px;align-items:center;flex-wrap:wrap">
  <input class="input" name="search" value="{{ $search }}" placeholder="Search by employee code, name, email, phone, department or unit" style="margin-top:0;flex:1;min-width:280px">
  @if($showDeleted)<input type="hidden" name="deleted" value="1">@endif
  <button class="btn" type="submit">Search</button>
  @if($search)<a class="btn gray" href="{{ route('admin.users.index', $showDeleted ? ['deleted'=>1] : []) }}">Clear</a>@endif
  @if($showDeleted)<a class="btn" href="{{ route('admin.users.index') }}">Active Users</a>@else<a class="btn gray" href="{{ route('admin.users.index',['deleted'=>1]) }}">Search Deleted</a>@endif
 </form>
 <div class="muted" style="margin-bottom:12px">{{ $users->total() }} {{ $showDeleted ? 'deleted' : '' }} user(s)</div>
 <div class="table-wrap"><table class="table" style="min-width:1250px"><thead><tr><th>Employee Code</th><th>Name</th><th>Email</th><th>Department</th><th>Unit</th><th>Job Title</th><th>Target WP</th><th>Group</th><th>Status</th><th>Actions</th></tr></thead><tbody>
 @forelse($users as $u)
 <tr>
  <td><strong>{{ $u->employee_code }}</strong></td><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->departmentRelation?->name ?? '—' }}</td><td>{{ $u->unit?->name ?? '—' }}</td><td>{{ $u->jobTitle?->name ?? '—' }}</td><td>{{ $u->jobTitle ? number_format((float)$u->jobTitle->target_workload_point,2).' WP' : '—' }}</td><td>{{ $u->group?->name ?? '—' }}</td>
  <td>{{ $u->registration_status === 'pending' ? 'Pending Approval' : ($u->registration_status === 'rejected' ? 'Rejected' : ($u->is_active ? 'Active' : 'Inactive')) }}</td>
  <td><div class="actions">
   @if(!$showDeleted && auth()->user()->hasPermission('users.edit'))<a class="btn gray" href="{{ route('admin.users.edit',$u) }}">Edit</a>@endif
   @if(!$showDeleted && auth()->user()->hasPermission('users.delete') && $u->id !== auth()->id())<form method="post" action="{{ route('admin.users.destroy',$u) }}">@csrf @method('DELETE')<button class="btn red" type="submit" onclick="return confirm('Delete this user? The record will be retained for history and can be restored later.')">Delete</button></form>@endif
   @if($showDeleted && auth()->user()->hasPermission('users.delete'))<form method="post" action="{{ route('admin.users.restore',$u->id) }}">@csrf @method('PATCH')<button class="btn" type="submit" onclick="return confirm('Restore this user?')">Restore</button></form>@endif
  </div></td>
 </tr>
 @empty
 <tr><td colspan="10" class="muted">{{ $showDeleted ? 'No deleted users found.' : 'No users found.' }}</td></tr>
 @endforelse
 </tbody></table></div>{{ $users->links() }}
</div>
@endsection
