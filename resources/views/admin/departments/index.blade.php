@extends('layouts.admin')
@section('title','Departments')
@section('content')
<div class="card">
 <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:15px">
  <form method="get" style="display:flex;gap:8px;flex:1;min-width:280px"><input class="input" name="search" value="{{ $search }}" placeholder="Search by code, department or description" style="margin-top:0"><button class="btn" type="submit">Search</button>@if($search)<a class="btn gray" href="{{ route('admin.departments.index') }}">Clear</a>@endif</form>
  <div class="actions">@if(auth()->user()->hasPermission('departments.create'))<a class="btn" href="{{ route('admin.departments.create') }}">+ New Department</a>@endif @if($showHidden)<a class="btn gray" href="{{ route('admin.departments.index',request()->except('show_hidden')) }}">Hide Hidden</a>@else<a class="btn gray" href="{{ route('admin.departments.index',array_merge(request()->query(),['show_hidden'=>1])) }}">Show Hidden</a>@endif</div>
 </div>
 <div class="muted" style="margin-bottom:12px">{{ $departments->total() }} department(s) · Master data used by employee profiles and KPI reporting.</div>
 <div class="table-wrap"><table class="table"><thead><tr><th>Code</th><th>Department</th><th>Description</th><th>Users</th><th>Status</th><th>Actions</th></tr></thead><tbody>
 @forelse($departments as $d)<tr><td><strong>{{ $d->code }}</strong></td><td>{{ $d->name }}</td><td>{{ $d->description ?? '—' }}</td><td>{{ $d->users_count }}</td><td>{{ $d->trashed()?'Hidden':($d->is_active?'Active':'Inactive') }}</td><td><div class="actions">@if(!$d->trashed()) @if(auth()->user()->hasPermission('departments.edit'))<a class="btn gray" href="{{ route('admin.departments.edit',$d) }}">Edit</a>@endif @if(auth()->user()->hasPermission('departments.delete'))<form method="post" action="{{ route('admin.departments.destroy',$d) }}">@csrf @method('DELETE')<button class="btn red" onclick="return confirm('Hide this department? The record will be retained.')">Hide</button></form>@endif @else @if(auth()->user()->hasPermission('departments.delete'))<form method="post" action="{{ route('admin.departments.restore',$d->id) }}">@csrf @method('PATCH')<button class="btn" type="submit">Restore</button></form>@endif @endif</div></td></tr>@empty<tr><td colspan="6" class="muted">No departments found.</td></tr>@endforelse
 </tbody></table></div>{{ $departments->links() }}
</div>
@endsection
