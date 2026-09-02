@extends('layouts.admin')
@section('title','Units')
@section('content')
<div class="card">
 <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:15px">
  <div><strong>Unit Management</strong><div class="muted">Organization master data used by employee profiles and KPI reporting.</div></div>
  <div class="actions">
   @if(auth()->user()->hasPermission('units.import'))<a class="btn gray" href="{{ route('admin.units.template') }}">Download Import Template</a>@endif
   @if(auth()->user()->hasPermission('units.export'))<a class="btn gray" href="{{ route('admin.units.export',request()->only('search','deleted')) }}">Export Excel</a>@endif
   @if(auth()->user()->hasPermission('units.import'))<form method="post" action="{{ route('admin.units.import') }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center"><input class="input" type="file" name="file" accept=".xlsx,.xls,.csv" required style="width:auto;margin-top:0">@csrf<button class="btn gray" type="submit">Import Excel</button></form>@endif
   @if(auth()->user()->hasPermission('units.create') && !$showDeleted)<a class="btn" href="{{ route('admin.units.create') }}">+ New Unit</a>@endif
  </div>
 </div>
 <form method="get" action="{{ route('admin.units.index') }}" style="display:flex;gap:8px;margin-bottom:15px;max-width:1100px;align-items:center;flex-wrap:wrap">
  <input class="input" name="search" value="{{ $search }}" placeholder="Search by code, unit name, address, phone or MST" style="margin-top:0;flex:1;min-width:280px">
  @if($showDeleted)<input type="hidden" name="deleted" value="1">@endif
  <button class="btn" type="submit">Search</button>
  @if($search)<a class="btn gray" href="{{ route('admin.units.index', $showDeleted ? ['deleted'=>1] : []) }}">Clear</a>@endif
  @if($showDeleted)<a class="btn" href="{{ route('admin.units.index') }}">Active Units</a>@else<a class="btn gray" href="{{ route('admin.units.index',['deleted'=>1]) }}">Search Deleted</a>@endif
 </form>
 <div class="muted" style="margin-bottom:12px">{{ $units->total() }} {{ $showDeleted ? 'deleted' : '' }} unit(s)</div>
 <div class="table-wrap"><table class="table"><thead><tr><th>Code</th><th>Unit Name</th><th>Address</th><th>Phone</th><th>MST</th><th>Users</th><th>Actions</th></tr></thead><tbody>
 @forelse($units as $u)
 <tr>
  <td><strong>{{ $u->code }}</strong></td><td>{{ $u->name }}</td><td>{{ $u->address ?: '—' }}</td><td>{{ $u->phone ?: '—' }}</td><td>{{ $u->tax_code ?: '—' }}</td><td>{{ $u->users_count }}</td>
  <td><div class="actions">
   @if(!$showDeleted && auth()->user()->hasPermission('units.edit'))<a class="btn gray" href="{{ route('admin.units.edit',$u) }}">Edit</a>@endif
   @if(!$showDeleted && auth()->user()->hasPermission('units.delete'))<form method="post" action="{{ route('admin.units.destroy',$u) }}">@csrf @method('DELETE')<button class="btn red" type="submit" onclick="return confirm('Delete this unit? The record will be retained for history and can be restored later.')">Delete</button></form>@endif
   @if($showDeleted && auth()->user()->hasPermission('units.delete'))<form method="post" action="{{ route('admin.units.restore',$u->id) }}">@csrf @method('PATCH')<button class="btn" type="submit" onclick="return confirm('Restore this unit?')">Restore</button></form>@endif
  </div></td>
 </tr>
 @empty
 <tr><td colspan="7" class="muted">{{ $showDeleted ? 'No deleted units found.' : 'No units found.' }}</td></tr>
 @endforelse
 </tbody></table></div>{{ $units->links() }}
</div>
@endsection
