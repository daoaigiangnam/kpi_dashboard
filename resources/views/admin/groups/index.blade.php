@extends('layouts.admin')
@section('title','User Groups')
@section('content')
<div class="card">
 <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:15px"><div class="muted">Access groups control dashboard and module permissions.</div><div class="actions">@if(auth()->user()->hasPermission('groups.create'))<a class="btn" href="{{ route('admin.groups.create') }}">+ New Group</a>@endif @if($showHidden)<a class="btn gray" href="{{ route('admin.groups.index') }}">Hide Hidden</a>@else<a class="btn gray" href="{{ route('admin.groups.index',['show_hidden'=>1]) }}">Show Hidden</a>@endif</div></div>
 <div class="table-wrap"><table class="table"><thead><tr><th>Group</th><th>Description</th><th>Users</th><th>Permissions</th><th>Status</th><th>Actions</th></tr></thead><tbody>
 @forelse($groups as $g)<tr><td>{{ $g->name }} @if($g->is_system)<span class="muted">(system)</span>@endif</td><td>{{ $g->description }}</td><td>{{ $g->users_count }}</td><td>{{ $g->permissions->count() }}</td><td>{{ $g->trashed()?'Hidden':'Active' }}</td><td><div class="actions">@if(!$g->trashed()) @if(auth()->user()->hasPermission('groups.edit'))<a class="btn gray" href="{{ route('admin.groups.edit',$g) }}">Edit</a>@endif @if(auth()->user()->hasPermission('groups.delete')&&!$g->is_system)<form method="post" action="{{ route('admin.groups.destroy',$g) }}">@csrf @method('DELETE')<button class="btn red" onclick="return confirm('Hide this group? The record will be retained.')">Hide</button></form>@endif @else @if(auth()->user()->hasPermission('groups.delete'))<form method="post" action="{{ route('admin.groups.restore',$g->id) }}">@csrf @method('PATCH')<button class="btn" type="submit">Restore</button></form>@endif @endif</div></td></tr>@empty<tr><td colspan="6" class="muted">No groups found.</td></tr>@endforelse
 </tbody></table></div>{{ $groups->links() }}
</div>
@endsection
