@extends('layouts.admin')

@section('title', 'Account Information')

@section('content')
<div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));align-items:start">
    <section class="card">
        <h2 style="margin-top:0">Personal Information</h2>
        <p class="muted">You can update your personal contact information. Employee code, email, organization and access role are managed by the administrator.</p>

        <form method="post" action="{{ route('account.update') }}" class="form" style="max-width:none">
            @csrf
            @method('PUT')

            <div class="field">
                <label>Employee Code</label>
                <input class="input" value="{{ $user->employee_code }}" disabled>
            </div>

            <div class="field">
                <label>Email / Login</label>
                <input class="input" value="{{ $user->email }}" disabled>
            </div>

            <div class="field">
                <label>Full Name *</label>
                <input class="input" name="name" value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="field">
                <label>Phone</label>
                <input class="input" name="phone" value="{{ old('phone', $user->phone) }}">
            </div>

            <div class="field">
                <label>Date of Birth</label>
                <input class="input" type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d')) }}">
            </div>

            <div class="field">
                <label>Gender</label>
                <select class="input" name="gender">
                    <option value="">— Select —</option>
                    <option value="Male" @selected(old('gender', $user->gender) === 'Male')>Male</option>
                    <option value="Female" @selected(old('gender', $user->gender) === 'Female')>Female</option>
                    <option value="Other" @selected(old('gender', $user->gender) === 'Other')>Other</option>
                </select>
            </div>

            <button class="btn" type="submit">Save Information</button>
        </form>
    </section>

    <section class="card">
        <h2 style="margin-top:0">Change Password</h2>
        <p class="muted">Change the password for your own account. You must enter your current password.</p>

        <form method="post" action="{{ route('account.password.update') }}" class="form" style="max-width:none">
            @csrf
            @method('PUT')

            <div class="field">
                <label>Current Password *</label>
                <input class="input" type="password" name="current_password" autocomplete="current-password" required>
            </div>

            <div class="field">
                <label>New Password *</label>
                <input class="input" type="password" name="password" autocomplete="new-password" minlength="8" required>
            </div>

            <div class="field">
                <label>Confirm New Password *</label>
                <input class="input" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
            </div>

            <button class="btn" type="submit">Change Password</button>
        </form>
    </section>
</div>
@endsection
