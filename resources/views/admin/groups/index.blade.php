@extends('layouts.admin')

@section('title','User Groups')

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:18px">
        <div>
            <div style="font-weight:700;font-size:16px">Access Groups</div>
            <div class="muted">Manage access groups, permissions and current user assignments. Deleted groups are hidden from access control but remain searchable for recovery.</div>
        </div>
        <div class="actions">
            @if(auth()->user()->hasPermission('groups.view'))
                <a class="btn gray" href="{{ route('admin.groups.export', request()->query()) }}">Export List</a>
            @endif
            @if(auth()->user()->hasPermission('groups.create'))
                <a class="btn" href="{{ route('admin.groups.create') }}">+ New Group</a>
            @endif
        </div>
    </div>

    <form method="get" action="{{ route('admin.groups.index') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:18px">
        <input class="input" style="max-width:520px;margin-top:0" type="search" name="search" value="{{ $search }}" placeholder="Search group, description, employee code, name or email">
        <button class="btn" type="submit">Search</button>
        @if($search !== '')
            <a class="btn gray" href="{{ route('admin.groups.index') }}">Clear</a>
        @endif
    </form>

    <div class="muted" style="margin-bottom:10px">
        {{ $groups->total() }} group(s) found. Hidden groups remain available here for Restore.
    </div>

    <div class="table-wrap">
        <table class="table" style="min-width:900px">
            <thead>
                <tr>
                    <th>Group</th>
                    <th>Description</th>
                    <th>Users</th>
                    <th>Permissions</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($groups as $g)
                <tr>
                    <td>
                        <strong>{{ $g->name }}</strong>
                        @if($g->is_system)
                            <span class="muted">(system)</span>
                        @endif
                    </td>
                    <td>{{ $g->description }}</td>
                    <td>
                        @if($g->users_count > 0)
                            <button
                                type="button"
                                class="btn gray"
                                style="padding:5px 9px;min-width:34px"
                                onclick="toggleGroupUsers({{ $g->id }})"
                                aria-expanded="false"
                                aria-controls="group-users-{{ $g->id }}"
                                id="group-users-button-{{ $g->id }}"
                            >{{ $g->users_count }}</button>
                        @else
                            <span class="muted">0</span>
                        @endif
                    </td>
                    <td>{{ $g->permissions->count() }}</td>
                    <td>
                        @if($g->trashed())
                            <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:12px">Hidden</span>
                        @else
                            <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:#e8f6ed;color:#24613f;font-size:12px">Active</span>
                        @endif
                    </td>
                    <td>
                        <div class="actions">
                            @if(!$g->trashed())
                                @if(auth()->user()->hasPermission('groups.edit'))
                                    <a class="btn gray" href="{{ route('admin.groups.edit',$g) }}">Edit</a>
                                @endif
                                @if(auth()->user()->hasPermission('groups.delete') && !$g->is_system)
                                    <form method="post" action="{{ route('admin.groups.destroy',$g) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            class="btn red"
                                            type="submit"
                                            onclick="return confirm({{ Js::from($g->users_count > 0 ? 'This group has '.$g->users_count.' assigned user(s). Deleting will hide the group, keep all user assignments for history, and remove the group permissions from active access control. Continue?' : 'Delete this group? It will be hidden, not physically removed, and can be restored later.') }})"
                                        >Delete</button>
                                    </form>
                                @endif
                            @else
                                @if(auth()->user()->hasPermission('groups.delete'))
                                    <form method="post" action="{{ route('admin.groups.restore',$g->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn" type="submit">Restore</button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>

                @if($g->users_count > 0)
                    <tr id="group-users-{{ $g->id }}" style="display:none;background:#f8fafc">
                        <td colspan="6" style="padding:16px 18px">
                            <div style="font-weight:700;margin-bottom:8px">Users in {{ $g->name }}</div>
                            <div class="table-wrap">
                                <table class="table" style="min-width:760px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
                                    <thead>
                                        <tr>
                                            <th>Employee Code</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Department</th>
                                            <th>Unit</th>
                                            <th>Job Title</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($g->users as $user)
                                            <tr>
                                                <td>{{ $user->employee_code ?: '—' }}</td>
                                                <td>{{ $user->name }}</td>
                                                <td>{{ $user->email }}</td>
                                                <td>{{ $user->departmentRelation?->name ?: '—' }}</td>
                                                <td>{{ $user->unit?->name ?: '—' }}</td>
                                                <td>{{ $user->jobTitle?->name ?: '—' }}</td>
                                                <td>{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="6" class="muted" style="text-align:center;padding:28px">No groups found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $groups->links() }}</div>
</div>

<script>
function toggleGroupUsers(id) {
    const row = document.getElementById('group-users-' + id);
    const button = document.getElementById('group-users-button-' + id);
    if (!row || !button) return;

    const open = row.style.display === 'table-row';
    row.style.display = open ? 'none' : 'table-row';
    button.setAttribute('aria-expanded', open ? 'false' : 'true');
}
</script>
@endsection
