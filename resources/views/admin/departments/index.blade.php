@extends('layouts.admin')
@section('title','Departments')
@section('content')
<div class="card">
 <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:15px">
  <div><strong>Department Management</strong><div class="muted">Organization master data used by employee profiles and KPI reporting.</div></div>
  <div class="actions">
   @if(auth()->user()->hasPermission('departments.import'))<a class="btn gray" href="{{ route('admin.departments.template') }}">Download Import Template</a>@endif
   @if(auth()->user()->hasPermission('departments.export'))<a class="btn gray" href="{{ route('admin.departments.export',request()->only('search','deleted')) }}">Export Excel</a>@endif
   @if(auth()->user()->hasPermission('departments.import'))<form method="post" action="{{ route('admin.departments.import') }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center"><input class="input" type="file" name="file" accept=".xlsx,.xls,.csv" required style="width:auto;margin-top:0">@csrf<button class="btn gray" type="submit">Import Excel</button></form>@endif
   @if(auth()->user()->hasPermission('departments.create') && !$showDeleted)<a class="btn" href="{{ route('admin.departments.create') }}">+ New Department</a>@endif
  </div>
 </div>
 <form method="get" action="{{ route('admin.departments.index') }}" style="display:flex;gap:8px;margin-bottom:15px;max-width:1100px;align-items:center;flex-wrap:wrap">
  <input class="input" name="search" value="{{ $search }}" placeholder="Search by code, department or description" style="margin-top:0;flex:1;min-width:280px">
  @if($showDeleted)<input type="hidden" name="deleted" value="1">@endif
  <button class="btn" type="submit">Search</button>
  @if($search)<a class="btn gray" href="{{ route('admin.departments.index', $showDeleted ? ['deleted'=>1] : []) }}">Clear</a>@endif
  @if($showDeleted)<a class="btn" href="{{ route('admin.departments.index') }}">Active Departments</a>@else<a class="btn gray" href="{{ route('admin.departments.index',['deleted'=>1]) }}">Search Deleted</a>@endif
 </form>
 <div class="muted" style="margin-bottom:12px">{{ $departments->total() }} {{ $showDeleted ? 'deleted' : '' }} department(s)</div>
 <div class="table-wrap"><table class="table"><thead><tr><th>Code</th><th>Department</th><th>Description</th><th>Users</th><th>Actions</th></tr></thead><tbody>
 @forelse($departments as $d)
 <tr>
  <td><strong>{{ $d->code }}</strong></td><td>{{ $d->name }}</td><td>{{ $d->description ?? '—' }}</td><td>{{ $d->users_count }}</td>
  <td><div class="actions">
   @if(!$showDeleted && auth()->user()->hasPermission('departments.edit'))<a class="btn gray" href="{{ route('admin.departments.edit',$d) }}">Edit</a>@endif
   @if(!$showDeleted && auth()->user()->hasPermission('departments.delete'))<form method="post" action="{{ route('admin.departments.destroy',$d) }}">@csrf @method('DELETE')<button class="btn red" type="submit" onclick="return confirm('Delete this department? The record will be retained for history and can be restored later.')">Delete</button></form>@endif
   @if($showDeleted && auth()->user()->hasPermission('departments.delete'))<form method="post" action="{{ route('admin.departments.restore',$d->id) }}">@csrf @method('PATCH')<button class="btn" type="submit" onclick="return confirm('Restore this department?')">Restore</button></form>@endif
  </div></td>
 </tr>
 @empty
 <tr><td colspan="5" class="muted">{{ $showDeleted ? 'No deleted departments found.' : 'No departments found.' }}</td></tr>
 @endforelse
 </tbody></table></div>{{ $departments->links() }}
</div>
@endsection
