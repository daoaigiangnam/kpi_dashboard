@extends('layouts.admin')
@section('title', $jobTitle->exists ? 'Edit Job Title' : 'New Job Title')
@section('content')
<div class="card form">
    <form method="post" action="{{ $jobTitle->exists ? route('admin.job_titles.update',$jobTitle) : route('admin.job_titles.store') }}">
        @csrf
        @if($jobTitle->exists) @method('PUT') @endif

        <div class="field">
            <label>Code *</label>
            <input class="input" name="code" value="{{ old('code',$jobTitle->code) }}" placeholder="e.g. IT-SUPPORT-L2" required>
            <div class="muted">Unique identifier. Letters, numbers, hyphens and underscores only.</div>
        </div>

        <div class="field">
            <label>Job Title *</label>
            <input class="input" name="name" value="{{ old('name',$jobTitle->name) }}" placeholder="e.g. IT Support Engineer" required>
        </div>

        <div class="field">
            <label>Level</label>
            <input class="input" name="level" value="{{ old('level',$jobTitle->level) }}" placeholder="e.g. L1, L2, L3, Lead">
        </div>

        <div class="field">
            <label>Target Workload Point (WP) *</label>
            <input class="input" type="number" name="target_workload_point" value="{{ old('target_workload_point',$jobTitle->target_workload_point ?? 0) }}" min="0" max="99999999.99" step="0.01" required>
            <div class="muted">Target workload point for this job title. The KPI calculation will use this value as the workload target.</div>
        </div>

        <div class="field">
            <label>Description</label>
            <input class="input" name="description" value="{{ old('description',$jobTitle->description) }}" maxlength="255" placeholder="Optional description">
        </div>

        <div class="check">
            <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$jobTitle->is_active ?? true))> Active</label>
        </div>

        <div style="margin-top:20px">
            <button class="btn" type="submit">{{ $jobTitle->exists ? 'Save Changes' : 'Create Job Title' }}</button>
            <a class="btn gray" href="{{ route('admin.job_titles.index') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
