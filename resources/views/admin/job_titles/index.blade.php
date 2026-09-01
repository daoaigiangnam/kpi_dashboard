@extends('layouts.admin')
@section('title','Job Titles')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:15px;flex-wrap:wrap">
        <div>
            <strong>Job Title Management</strong>
            <div class="muted">Define job titles and the Target Workload Point used by KPI calculation.</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @if(auth()->user()->hasPermission('job_titles.export'))
                <a class="btn gray" href="{{ route('admin.job_titles.export', ['search' => $search]) }}">Export Excel</a>
            @endif
            @if(auth()->user()->hasPermission('job_titles.import'))
                <form method="post" action="{{ route('admin.job_titles.import') }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    @csrf
                    <input class="input" type="file" name="file" accept=".xlsx,.xls,.csv" required style="width:auto;margin-top:0">
                    <button class="btn gray" type="submit">Import Excel</button>
                </form>
            @endif
            @if(auth()->user()->hasPermission('job_titles.create'))
                <a class="btn" href="{{ route('admin.job_titles.create') }}">+ New Job Title</a>
            @endif
        </div>
    </div>

    <form method="get" action="{{ route('admin.job_titles.index') }}" style="display:flex;gap:8px;margin-bottom:15px;max-width:720px">
        <input class="input" type="search" name="search" value="{{ $search }}" placeholder="Search by code, job title, level or description" style="margin-top:0">
        <button class="btn" type="submit">Search</button>
        @if($search !== '')
            <a class="btn gray" href="{{ route('admin.job_titles.index') }}">Clear</a>
        @endif
    </form>

    <div class="muted" style="margin-bottom:10px">{{ $jobTitles->total() }} job title(s)</div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Job Title</th>
                    <th>Level</th>
                    <th>Target Workload Point</th>
                    <th>Users</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jobTitles as $jobTitle)
                    <tr>
                        <td><strong>{{ $jobTitle->code }}</strong></td>
                        <td>{{ $jobTitle->name }}@if($jobTitle->description)<div class="muted">{{ $jobTitle->description }}</div>@endif</td>
                        <td>{{ $jobTitle->level ?: '—' }}</td>
                        <td><strong>{{ number_format((float) $jobTitle->target_workload_point, 2) }}</strong> WP</td>
                        <td>{{ $jobTitle->users_count }}</td>
                        <td>{{ $jobTitle->is_active ? 'Active' : 'Inactive' }}</td>
                        <td>
                            @if(auth()->user()->hasPermission('job_titles.edit'))
                                <a class="btn gray" href="{{ route('admin.job_titles.edit',$jobTitle) }}">Edit</a>
                            @endif
                            @if(auth()->user()->hasPermission('job_titles.delete'))
                                <form style="display:inline" method="post" action="{{ route('admin.job_titles.destroy',$jobTitle) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn red" onclick="return confirm('Delete this job title?')">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No job titles found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $jobTitles->links() }}
</div>
@endsection
