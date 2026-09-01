@extends('layouts.admin')
@section('title','Users')
@section('content')
<div class="card">
    <div style="margin-bottom:15px">
        @if(auth()->user()->hasPermission('users.create'))
            <a class="btn" href="{{ route('admin.users.create') }}">+ New User</a>
        @endif
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Group</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($users as $u)
                <tr>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td>{{ $u->group?->name ?? '—' }}</td>
                    <td>{{ $u->is_active ? 'Active':'Inactive' }}</td>
                    <td>
                        <div class="actions">
                            @if(auth()->user()->hasPermission('users.edit'))
                                <a class="btn gray" href="{{ route('admin.users.edit',$u) }}">Edit</a>
                            @endif
                            @if(auth()->user()->hasPermission('users.delete'))
                                <form method="post" action="{{ route('admin.users.destroy',$u) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn red" onclick="return confirm('Delete this user?')">Delete</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</div>
@endsection
