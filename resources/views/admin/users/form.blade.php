@extends('layouts.admin')
@section('title', $user->exists ? 'Edit User' : 'New User')
@section('content')
<form method="post" action="{{ $user->exists ? route('admin.users.update',$user) : route('admin.users.store') }}" style="display:flex;flex-direction:column;gap:16px;max-width:900px">
@csrf @if($user->exists) @method('PUT') @endif

<div class="card">
 <h3 style="margin-top:0">Employee Information</h3>
 <div class="muted" style="margin-bottom:16px">Personal information, organization and access role.</div>
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
</div>

<div class="card">
 <h3 style="margin-top:0">Account &amp; Security</h3>
 <div class="muted" style="margin-bottom:16px">The user signs in with Email. Passwords are never entered by an administrator.</div>
 <div class="field" style="margin-bottom:0">
  <label>Account Status</label>
  <div style="margin-top:6px"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$user->exists ? $user->is_active : true))> Active — user can log in and access permitted dashboards</label></div>
 </div>
 @if($user->exists)
 <div style="margin-top:16px;padding-top:16px;border-top:1px solid #dbe3e8;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
  <div>
   <strong>Password</strong>
   <div class="muted" style="margin-top:4px">Send the user a new OTP and secure link to create/change their password.</div>
  </div>
  <button class="btn gray" type="submit" form="reset-password-form" onclick="return confirm('Send a new password reset email?')">Reset Password</button>
 </div>
 @else
 <div style="margin-top:16px;padding:12px 14px;background:#f0f7f3;border:1px solid #cfe4d7;border-radius:8px;color:#245b38">
  After saving the employee record, the system automatically sends the user an email containing an OTP and secure link to create the first password.
 </div>
 @endif
</div>

<div>
 <button class="btn" type="submit">Save User</button>
 <a class="btn gray" href="{{ route('admin.users.index') }}">Cancel</a>
</div>
</form>
@if($user->exists)
<form id="reset-password-form" method="post" action="{{ route('admin.users.reset_password',$user) }}" style="display:none">@csrf</form>
@endif
@endsection
