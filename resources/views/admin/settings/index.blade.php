@extends('layouts.admin')

@section('title', 'System Settings')

@section('content')
<div class="card form" style="max-width:900px">
    <div style="margin-bottom:22px">
        <h2 style="margin:0 0 6px">System Settings</h2>
        <div class="muted">Central configuration for email delivery, password recovery, login security and future KPI parameters.</div>
    </div>

    <form method="post" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')
        <h3 style="margin:0 0 14px">Email / SMTP</h3>
        <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:24px">
            <div class="field"><label>Mail Driver</label><select name="mail_mailer" class="input"><option value="log" @selected($settings['mail.mailer'] === 'log')>Log (development)</option><option value="smtp" @selected($settings['mail.mailer'] === 'smtp')>SMTP</option></select></div>
            <div class="field"><label>SMTP Host</label><input name="mail_host" class="input" value="{{ old('mail_host', $settings['mail.host']) }}" placeholder="smtp.example.com"></div>
            <div class="field"><label>SMTP Port</label><input name="mail_port" type="number" class="input" value="{{ old('mail_port', $settings['mail.port']) }}"></div>
            <div class="field"><label>Encryption</label><select name="mail_encryption" class="input">@foreach(['none' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL'] as $value => $label)<option value="{{ $value }}" @selected($settings['mail.encryption'] === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label>SMTP Username / Email</label><input name="mail_username" class="input" value="{{ old('mail_username', $settings['mail.username']) }}"></div>
            <div class="field"><label>SMTP Password</label><input name="mail_password" type="password" class="input" autocomplete="new-password" placeholder="{{ $mailPasswordConfigured ? 'Configured — leave blank to keep current password' : 'Enter SMTP password' }}"><div class="muted" style="margin-top:5px">Stored encrypted in the database; never stored in .env or displayed back to the browser.</div></div>
            <div class="field"><label>From Email</label><input name="mail_from_address" type="email" class="input" value="{{ old('mail_from_address', $settings['mail.from_address']) }}"></div>
            <div class="field"><label>From Name</label><input name="mail_from_name" class="input" value="{{ old('mail_from_name', $settings['mail.from_name']) }}"></div>
        </div>

        <h3 style="margin:0 0 14px">Password Recovery</h3>
        <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:24px">
            <div class="field"><label>OTP Expiry (minutes)</label><input name="otp_expire_minutes" type="number" min="1" max="60" class="input" value="{{ old('otp_expire_minutes', $settings['password_reset.otp_expire_minutes']) }}"></div>
            <div class="field"><label>Reset Link Expiry (minutes)</label><input name="link_expire_minutes" type="number" min="5" max="1440" class="input" value="{{ old('link_expire_minutes', $settings['password_reset.link_expire_minutes']) }}"></div>
            <div class="field"><label>Maximum OTP Attempts</label><input name="max_otp_attempts" type="number" min="1" max="20" class="input" value="{{ old('max_otp_attempts', $settings['password_reset.max_otp_attempts']) }}"></div>
        </div>

        <h3 style="margin:0 0 14px">Login Security</h3>
        <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:24px">
            <div class="field">
                <label>Failed Login Attempts Before Lock</label>
                <input name="login_max_attempts" type="number" min="1" max="20" class="input" value="{{ old('login_max_attempts', $settings['security.login_max_attempts']) }}">
                <div class="muted" style="margin-top:5px">The CAPTCHA must still be solved before credentials are checked.</div>
            </div>
            <div class="field">
                <label>Login Lockout Duration (minutes)</label>
                <input name="login_lockout_minutes" type="number" min="1" max="1440" class="input" value="{{ old('login_lockout_minutes', $settings['security.login_lockout_minutes']) }}">
                <div class="muted" style="margin-top:5px">Lock is tracked by email + client IP and uses the application cache, so repeated bad passwords stop reaching authentication.</div>
            </div>
        </div>

        <div style="padding:14px 16px;background:#f6f9f7;border:1px solid #e1e9e4;border-radius:8px;margin-bottom:20px">
            <strong>KPI Configuration</strong>
            <div class="muted" style="margin-top:5px">Reserved for KPI calculation thresholds, workload rules and scoring weights. New KPI parameters can be added here without changing the settings architecture.</div>
        </div>

        <div class="actions">
            <button class="btn" type="submit">Save Settings</button>
        </div>
    </form>

    <form method="post" action="{{ route('admin.settings.test-mail') }}" style="margin-top:10px">
        @csrf
        <button class="btn gray" type="submit">Send Test Email to My Account</button>
    </form>
</div>
@endsection
