@extends('layouts.admin')
@section('title','Job Titles')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:15px">
        <div>
            <strong>Job Title Management</strong>
            <div class="muted">Define job titles and the Target Workload Point used by KPI calculation.</div>
        </div>
        @if(auth()->user()->hasPermission('job_titles.create'))
            <a class="btn" href="{{ route('admin.job_titles.create') }}">+ New Job Title</a>
        @endif
    </div>
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
