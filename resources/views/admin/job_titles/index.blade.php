@extends('layouts.admin')

@section('title','Job Titles')

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:18px">
        <div>
            <div style="font-weight:700;font-size:16px">Job Title Management</div>
            <div class="muted">Manage job titles and Target Workload Point used by KPI calculation. Hidden job titles are removed from the normal list and can be recovered from Find Hidden.</div>
        </div>
        <div class="actions">
            @if(auth()->user()->hasPermission('job_titles.import'))
                <a class="btn gray" href="{{ route('admin.job_titles.template') }}">Download Import Template</a>
            @endif
            @if(auth()->user()->hasPermission('job_titles.export'))
                <a class="btn gray" href="{{ route('admin.job_titles.export',request()->query()) }}">Export Excel</a>
            @endif
            @if(auth()->user()->hasPermission('job_titles.import'))
                <form method="post" action="{{ route('admin.job_titles.import') }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center">
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

    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:18px">
        <form method="get" action="{{ route('admin.job_titles.index') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex:1;min-width:280px">
            <input type="hidden" name="show_hidden" value="{{ $showHidden ? 1 : 0 }}">
            <input class="input" style="max-width:520px;margin-top:0" type="search" name="search" value="{{ $search }}" placeholder="Search code, job title, level or description">
            <button class="btn" type="submit">Search</button>
            @if($search !== '')
                <a class="btn gray" href="{{ route('admin.job_titles.index', ['show_hidden'=>$showHidden ? 1 : 0]) }}">Clear</a>
            @endif
        </form>
        @if(auth()->user()->hasPermission('job_titles.delete'))
            @if($showHidden)
                <a class="btn gray" href="{{ route('admin.job_titles.index') }}">← Active Job Titles</a>
            @else
                <a class="btn gray" href="{{ route('admin.job_titles.index', ['show_hidden'=>1]) }}">Find Hidden</a>
            @endif
        @endif
    </div>

    <div class="muted" style="margin-bottom:10px">
        @if($showHidden)
            {{ $jobTitles->total() }} hidden job title(s) found. Select Restore to return a job title to the active list.
        @else
            {{ $jobTitles->total() }} active job title(s) found. Hidden job titles are not displayed here.
        @endif
    </div>

    <div class="table-wrap">
        <table class="table" style="min-width:1050px">
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
            @forelse($jobTitles as $jt)
                <tr>
                    <td><strong>{{ $jt->code }}</strong></td>
                    <td>
                        {{ $jt->name }}
                        @if($jt->description)<div class="muted">{{ $jt->description }}</div>@endif
                    </td>
                    <td>{{ $jt->level ?: '—' }}</td>
                    <td><strong>{{ number_format((float)$jt->target_workload_point,2) }}</strong> WP</td>
                    <td>
                        @if($jt->users_count > 0)
                            <button type="button" class="btn gray" style="padding:5px 9px;min-width:34px" onclick="showJobTitleUsers({{ $jt->id }})" aria-expanded="false" aria-controls="job-title-users-detail-{{ $jt->id }}" id="job-title-users-button-{{ $jt->id }}">{{ $jt->users_count }}</button>
                        @else
                            <span class="muted">0</span>
                        @endif
                    </td>
                    <td>
                        @if($showHidden)
                            <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:12px">Hidden</span>
                        @elseif($jt->is_active)
                            <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:#e8f6ed;color:#24613f;font-size:12px">Active</span>
                        @else
                            <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:12px">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="actions">
                            @if($showHidden)
                                @if(auth()->user()->hasPermission('job_titles.delete'))
                                    <form method="post" action="{{ route('admin.job_titles.restore',$jt->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn" type="submit">Restore</button>
                                    </form>
                                @endif
                            @else
                                @if(auth()->user()->hasPermission('job_titles.edit'))
                                    <a class="btn gray" href="{{ route('admin.job_titles.edit',$jt) }}">Edit</a>
                                @endif
                                @if(auth()->user()->hasPermission('job_titles.delete'))
                                    @if($jt->users_count === 0)
                                        <form method="post" action="{{ route('admin.job_titles.destroy',$jt) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn red" type="submit" onclick="return confirm('Hide this job title? It will not be physically deleted and can be restored later from Find Hidden.')">Delete</button>
                                        </form>
                                    @else
                                        <button class="btn gray" type="button" disabled title="Remove all assigned users before hiding this job title." style="opacity:.55;cursor:not-allowed">Delete</button>
                                    @endif
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted" style="text-align:center;padding:28px">{{ $showHidden ? 'No hidden job titles found.' : 'No active job titles found.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $jobTitles->links() }}</div>
</div>

@foreach($jobTitles as $jt)
    @if($jt->users_count > 0)
        <div id="job-title-users-detail-{{ $jt->id }}" class="card job-title-users-detail" style="display:none;margin-top:18px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px">
                <div>
                    <div style="font-weight:700;font-size:16px">Users in {{ $jt->name }}</div>
                    <div class="muted">{{ $jt->users_count }} assigned user(s). Remove users here before deleting this job title.</div>
                </div>
                <button type="button" class="btn gray" onclick="hideJobTitleUsers({{ $jt->id }})">Close</button>
            </div>
            <div class="table-wrap">
                <table class="table" style="min-width:1050px">
                    <thead>
                        <tr>
                            <th>Employee Code</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Unit</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($jt->users as $user)
                            <tr>
                                <td>{{ $user->employee_code ?: '—' }}</td>
                                <td><strong>{{ $user->name }}</strong></td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->departmentRelation?->name ?: '—' }}</td>
                                <td>{{ $user->unit?->name ?: '—' }}</td>
                                <td>
                                    @if($user->is_active)
                                        <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:#e8f6ed;color:#24613f;font-size:12px">Active</span>
                                    @else
                                        <span style="display:inline-block;padding:4px 8px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:12px">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if(auth()->user()->hasPermission('job_titles.edit'))
                                        <form method="post" action="{{ route('admin.job_titles.users.remove', [$jt, $user]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn red" type="submit" onclick="return confirm({{ Js::from('Remove '.$user->name.' from '.$jt->name.'? The user account will not be deleted.') }})">Remove</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endforeach

<script>
function showJobTitleUsers(id) {
    document.querySelectorAll('.job-title-users-detail').forEach(function(panel){ panel.style.display='none'; });
    document.querySelectorAll('[id^="job-title-users-button-"]').forEach(function(button){ button.setAttribute('aria-expanded','false'); });
    const panel=document.getElementById('job-title-users-detail-'+id);
    const button=document.getElementById('job-title-users-button-'+id);
    if(!panel||!button)return;
    panel.style.display='block';
    button.setAttribute('aria-expanded','true');
    panel.scrollIntoView({behavior:'smooth',block:'start'});
}
function hideJobTitleUsers(id) {
    const panel=document.getElementById('job-title-users-detail-'+id);
    const button=document.getElementById('job-title-users-button-'+id);
    if(!panel||!button)return;
    panel.style.display='none';
    button.setAttribute('aria-expanded','false');
}
</script>
@endsection
