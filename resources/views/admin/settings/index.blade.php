@extends('layouts.admin')

@section('title', 'System Settings')

@section('content')
<div class="card form" style="max-width:900px">
    <div style="margin-bottom:22px">
        <h2 style="margin:0 0 6px">System Settings</h2>
        <div class="muted">Central configuration for email delivery, registration notifications, password recovery, login security and IT Monitoring alert emails.</div>
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
            <div class="field"><label>SMTP Password</label><input name="mail_password" type="password" class="input" autocomplete="new-password" placeholder="{{ $mailPasswordConfigured ? 'Configured — leave blank to keep current password' : 'Enter SMTP password' }}"><div class="muted" style="margin-top:5px">Stored encrypted in the database.</div></div>
            <div class="field"><label>From Email</label><input name="mail_from_address" type="email" class="input" value="{{ old('mail_from_address', $settings['mail.from_address']) }}"></div>
            <div class="field"><label>From Name</label><input name="mail_from_name" class="input" value="{{ old('mail_from_name', $settings['mail.from_name']) }}"></div>
        </div>

        <div style="padding:15px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:24px">
            <strong>System Notification Email</strong>
            <div class="muted" style="margin:5px 0 10px">System notifications and new self-service registration requests.</div>
            <input name="notification_email" type="email" class="input" value="{{ old('notification_email', $settings['system.notification_email']) }}" required placeholder="admin@example.com">
        </div>

        <h3 style="margin:0 0 14px">IT Monitoring - Alert Email</h3>
        <div style="padding:15px 16px;background:#f8fafc;border:1px solid #dbe3ec;border-radius:10px;margin-bottom:24px">
            <label style="display:flex;align-items:center;gap:10px;margin-bottom:16px;cursor:pointer"><input type="hidden" name="alert_email_enabled" value="0"><input type="checkbox" name="alert_email_enabled" value="1" @checked($settings['alert_email.enabled'] === '1')> <strong>Enable Alert Email Escalation</strong></label>
            <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:18px">
                <label style="display:flex;align-items:center;gap:10px"><input type="hidden" name="alert_operator_enabled" value="0"><input type="checkbox" name="alert_operator_enabled" value="1" @checked($settings['alert_email.operator_enabled'] === '1')> NV vận hành - gửi ngay</label>
                <label style="display:flex;align-items:center;gap:10px"><input type="hidden" name="alert_it_lead_enabled" value="0"><input type="checkbox" name="alert_it_lead_enabled" value="1" @checked($settings['alert_email.it_lead_enabled'] === '1')> IT Lead</label>
                <label style="display:flex;align-items:center;gap:10px"><input type="hidden" name="alert_bod_enabled" value="0"><input type="checkbox" name="alert_bod_enabled" value="1" @checked($settings['alert_email.bod_enabled'] === '1')> BOD Outsourcing</label>
                <label style="display:flex;align-items:center;gap:10px"><input type="hidden" name="alert_customer_enabled" value="0"><input type="checkbox" name="alert_customer_enabled" value="1" @checked($settings['alert_email.customer_enabled'] === '1')> Khách hàng</label>
            </div>
            <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:18px">
                <div class="field"><label>Email IT Lead</label><input name="alert_it_lead_email" type="email" class="input" value="{{ old('alert_it_lead_email', $settings['alert_email.it_lead_email']) }}" placeholder="itlead@example.com"></div>
                <div class="field"><label>Email BOD Outsourcing</label><input name="alert_bod_email" type="email" class="input" value="{{ old('alert_bod_email', $settings['alert_email.bod_email']) }}" placeholder="bod@example.com"></div>
                <div class="field"><label>IT Lead sau (phút)</label><input name="alert_it_lead_delay_minutes" type="number" min="1" max="10080" class="input" value="{{ old('alert_it_lead_delay_minutes', $settings['alert_email.it_lead_delay_minutes']) }}"></div>
                <div class="field"><label>BOD sau (phút)</label><input name="alert_bod_delay_minutes" type="number" min="1" max="10080" class="input" value="{{ old('alert_bod_delay_minutes', $settings['alert_email.bod_delay_minutes']) }}"></div>
                <div class="field"><label>Khách hàng sau (phút)</label><input name="alert_customer_delay_minutes" type="number" min="1" max="10080" class="input" value="{{ old('alert_customer_delay_minutes', $settings['alert_email.customer_delay_minutes']) }}"></div>
            </div>
            <label style="display:flex;align-items:center;gap:10px"><input type="hidden" name="alert_send_resolution" value="0"><input type="checkbox" name="alert_send_resolution" value="1" @checked($settings['alert_email.send_resolution'] === '1')> Gửi Resolution Report cho các cấp khi Alert được Resolve</label>
            <div class="muted" style="margin-top:10px">NV vận hành lấy email từ Responsible IT của Service. Khách hàng lấy email từ Customer. IT Lead và BOD dùng email cấu hình trên.</div>
        </div>

        <h3 style="margin:0 0 14px">User Registration</h3>
        <div style="padding:15px 16px;background:#f8fafc;border:1px solid #dbe3ec;border-radius:10px;margin-bottom:24px">
            <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer"><input type="hidden" name="allow_self_registration" value="0"><input type="checkbox" name="allow_self_registration" value="1" @checked($settings['security.allow_self_registration'] === '1') style="margin-top:3px"><span><strong>Allow Self-Service Signup</strong><span class="muted" style="display:block;margin-top:4px">Show the Sign up option on the Login page and allow users to start the email-verified registration process.</span></span></label>
        </div>

        <h3 style="margin:0 0 14px">Password Recovery</h3>
        <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:24px">
            <div class="field"><label>OTP Expiry (minutes)</label><input name="otp_expire_minutes" type="number" min="1" max="60" class="input" value="{{ old('otp_expire_minutes', $settings['password_reset.otp_expire_minutes']) }}"></div>
            <div class="field"><label>Reset Link Expiry (minutes)</label><input name="link_expire_minutes" type="number" min="5" max="1440" class="input" value="{{ old('link_expire_minutes', $settings['password_reset.link_expire_minutes']) }}"></div>
            <div class="field"><label>Maximum OTP Attempts</label><input name="max_otp_attempts" type="number" min="1" max="20" class="input" value="{{ old('max_otp_attempts', $settings['password_reset.max_otp_attempts']) }}"></div>
        </div>

        <h3 style="margin:0 0 14px">Login Security</h3>
        <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:24px">
            <div class="field"><label>Failed Login Attempts Before Lock</label><input name="login_max_attempts" type="number" min="1" max="20" class="input" value="{{ old('login_max_attempts', $settings['security.login_max_attempts']) }}"></div>
            <div class="field"><label>Login Lockout Duration (minutes)</label><input name="login_lockout_minutes" type="number" min="1" max="1440" class="input" value="{{ old('login_lockout_minutes', $settings['security.login_lockout_minutes']) }}"></div>
        </div>

        <div class="actions"><button class="btn" type="submit">Save Settings</button></div>
    </form>

    <form method="post" action="{{ route('admin.settings.test-mail') }}" style="margin-top:10px">
        @csrf
        <button class="btn gray" type="submit">Send Test Email to My Account</button>
    </form>
</div>
@endsection
