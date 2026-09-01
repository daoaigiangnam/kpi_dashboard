@extends('layouts.admin')
@section('title','Units')
@section('content')
<div class="card">
 <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:15px">
  <form method="get" style="display:flex;gap:8px;flex:1;min-width:280px"><input class="input" name="search" value="{{ $search }}" placeholder="Search by code, unit or description" style="margin-top:0"><button class="btn" type="submit">Search</button>@if($search)<a class="btn gray" href="{{ route('admin.units.index') }}">Clear</a>@endif</form>
  <div class="actions">@if(auth()->user()->hasPermission('units.create'))<a class="btn" href="{{ route('admin.units.create') }}">+ New Unit</a>@endif @if($showHidden)<a class="btn gray" href="{{ route('admin.units.index',request()->except('show_hidden')) }}">Hide Hidden</a>@else<a class="btn gray" href="{{ route('admin.units.index',array_merge(request()->query(),['show_hidden'=>1])) }}">Show Hidden</a>@endif</div>
 </div>
 <div class="muted" style="margin-bottom:12px">{{ $units->total() }} unit(s) · Master data used by employee profiles and KPI reporting.</div>
 <div class="table-wrap"><table class="table"><thead><tr><th>Code</th><th>Unit</th><th>Description</th><th>Users</th><th>Status</th><th>Actions</th></tr></thead><tbody>
 @forelse($units as $u)<tr><td><strong>{{ $u->code }}</strong></td><td>{{ $u->name }}</td><td>{{ $u->description ?? '—' }}</td><td>{{ $u->users_count }}</td><td>{{ $u->trashed()?'Hidden':($u->is_active?'Active':'Inactive') }}</td><td><div class="actions">@if(!$u->trashed()) @if(auth()->user()->hasPermission('units.edit'))<a class="btn gray" href="{{ route('admin.units.edit',$u) }}">Edit</a>@endif @if(auth()->user()->hasPermission('units.delete'))<form method="post" action="{{ route('admin.units.destroy',$u) }}">@csrf @method('DELETE')<button class="btn red" onclick="return confirm('Hide this unit? The record will be retained.')">Hide</button></form>@endif @else @if(auth()->user()->hasPermission('units.delete'))<form method="post" action="{{ route('admin.units.restore',$u->id) }}">@csrf @method('PATCH')<button class="btn" type="submit">Restore</button></form>@endif @endif</div></td></tr>@empty<tr><td colspan="6" class="muted">No units found.</td></tr>@endforelse
 </tbody></table></div>{{ $units->links() }}
</div>
@endsection
