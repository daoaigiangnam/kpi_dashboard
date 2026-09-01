@extends('layouts.admin')
@section('title', $user->exists ? 'Edit User' : 'New User')
@section('content')
<div class="card form">
<form method="post" action="{{ $user->exists ? route('admin.users.update',$user) : route('admin.users.store') }}">
@csrf @if($user->exists) @method('PUT') @endif
<h3 style="margin-top:0">Employee Information</h3>
<div class="grid" style="grid-template-columns:1fr 1fr">
<div class="field"><label>Employee Code *</label><input class="input" name="employee_code" value="{{ old('employee_code',$user->employee_code) }}" required placeholder="EMP-0001"></div>
<div class="field"><label>Full Name *</label><input class="input" name="name" value="{{ old('name',$user->name) }}" required></div>
<div class="field"><label>Email / Login *</label><input class="input" type="email" name="email" value="{{ old('email',$user->email) }}" required></div>
<div class="field"><label>Phone</label><input class="input" name="phone" value="{{ old('phone',$user->phone) }}"></div>
<div class="field"><label>Date of Birth</label><input class="input" type="date" name="date_of_birth" value="{{ old('date_of_birth',$user->date_of_birth?->format('Y-m-d')) }}"></div>
<div class="field"><label>Gender</label><select class="input" name="gender"><option value="">— Select —</option>@foreach(['Male','Female','Other'] as $gender)<option value="{{ $gender }}" @selected(old('gender',$user->gender)===$gender)>{{ $gender }}</option>@endforeach</select></div>
<div class="field"><label>Join Date</label><input class="input" type="date" name="join_date" value="{{ old('join_date',$user->join_date?->format('Y-m-d')) }}"></div>
<div class="field"><label>Department</label><select class="input" name="department_id"><option value="">— Select Department —</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id',$user->department_id)==$d->id)>{{ $d->name }}{{ $d->is_active?'':' (Inactive)' }}</option>@endforeach</select></div>
<div class="field"><label>Unit</label><select class="input" name="unit_id"><option value="">— Select Unit —</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected(old('unit_id',$user->unit_id)==$u->id)>{{ $u->name }}{{ $u->is_active?'':' (Inactive)' }}</option>@endforeach</select></div>
<div class="field"><label>User Group / Access Role</label><select class="input" name="user_group_id"><option value="">— No Group —</option>@foreach($groups as $g)<option value="{{ $g->id }}" @selected(old('user_group_id',$user->user_group_id)==$g->id)>{{ $g->name }}</option>@endforeach</select></div>
</div>
<h3>KPI Assignment</h3>
<div class="field"><label>Job Title</label><select class="input" name="job_title_id"><option value="">— No Job Title —</option>@foreach($jobTitles as $jt)<option value="{{ $jt->id }}" @selected(old('job_title_id',$user->job_title_id)==$jt->id)>{{ $jt->name }} · {{ $jt->level ?? '—' }} · {{ number_format((float)$jt->target_workload_point,2) }} WP{{ $jt->is_active?'':' (Inactive)' }}</option>@endforeach</select><div class="muted" style="margin-top:5px">Target Workload Point is inherited from the selected Job Title and is used by KPI calculation.</div></div>
<div class="field"><label>Notes</label><textarea class="input" name="notes" rows="3" maxlength="500">{{ old('notes',$user->notes) }}</textarea></div>
<h3>Account</h3>
<div class="field"><label>Password {{ $user->exists ? '(leave blank to keep current)' : '*' }}</label><input class="input" type="password" name="password" {{ $user->exists ? '' : 'required' }} minlength="8"></div>
<div class="field"><label>Confirm Password</label><input class="input" type="password" name="password_confirmation" {{ $user->exists ? '' : 'required' }} minlength="8"></div>
<div class="field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$user->exists ? $user->is_active : true))> Active — user can log in and access permitted dashboards</label></div>
<button class="btn">Save User</button> <a class="btn gray" href="{{ route('admin.users.index') }}">Cancel</a>
</form>
</div>
@endsection
