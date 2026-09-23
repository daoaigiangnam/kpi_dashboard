<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PcAuditSetting;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SystemSettingController extends Controller
{
    private const DEFAULTS = [
        'mail.mailer' => 'log', 'mail.host' => '', 'mail.port' => '587', 'mail.encryption' => 'tls',
        'mail.username' => '', 'mail.password' => '', 'mail.from_address' => '', 'mail.from_name' => 'KPI Dashboard System',
        'system.notification_email' => '',
        'security.allow_self_registration' => '1',
        'password_reset.otp_expire_minutes' => '10', 'password_reset.link_expire_minutes' => '60', 'password_reset.max_otp_attempts' => '5',
        'security.login_max_attempts' => '5', 'security.login_lockout_minutes' => '15',
        'alert_email.enabled' => '0', 'alert_email.operator_enabled' => '1', 'alert_email.it_lead_enabled' => '1',
        'alert_email.bod_enabled' => '1', 'alert_email.customer_enabled' => '1', 'alert_email.send_resolution' => '1',
        'alert_email.it_lead_email' => '', 'alert_email.bod_email' => '',
        'alert_email.it_lead_delay_minutes' => '30', 'alert_email.bod_delay_minutes' => '60', 'alert_email.customer_delay_minutes' => '120',
    ];

    public function index(): mixed
    {
        $settings = collect(self::DEFAULTS)->mapWithKeys(fn ($default, $key) => [$key => SystemSetting::value($key, $default)]);
        $pcAudit = PcAuditSetting::query()->first() ?? new PcAuditSetting([
            'api_base_url' => config('app.url') . '/api',
            'tool_version' => '1.0.0',
            'minimum_tool_version' => '1.0.0',
            'enabled' => true,
        ]);

        return view('admin.settings.index', [
            'settings' => $settings,
            'pcAudit' => $pcAudit,
            'mailPasswordConfigured' => filled(SystemSetting::value('mail.password')),
        ]);
    }

    public function update(Request $request): mixed
    {
        $data = $request->validate([
            'mail_mailer' => ['required', 'in:log,smtp'], 'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['required', 'integer', 'min:1', 'max:65535'], 'mail_encryption' => ['required', 'in:none,tls,ssl'],
            'mail_username' => ['nullable', 'string', 'max:255'], 'mail_password' => ['nullable', 'string', 'max:1000'],
            'mail_from_address' => ['nullable', 'email', 'max:255'], 'mail_from_name' => ['required', 'string', 'max:150'],
            'notification_email' => ['required', 'email', 'max:255'],
            'allow_self_registration' => ['required', 'boolean'],
            'otp_expire_minutes' => ['required', 'integer', 'min:1', 'max:60'], 'link_expire_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'max_otp_attempts' => ['required', 'integer', 'min:1', 'max:20'],
            'login_max_attempts' => ['required', 'integer', 'min:1', 'max:20'],
            'login_lockout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'alert_email_enabled' => ['required', 'boolean'], 'alert_operator_enabled' => ['required', 'boolean'],
            'alert_it_lead_enabled' => ['required', 'boolean'], 'alert_bod_enabled' => ['required', 'boolean'],
            'alert_customer_enabled' => ['required', 'boolean'], 'alert_send_resolution' => ['required', 'boolean'],
            'alert_it_lead_email' => ['nullable', 'email', 'max:255'], 'alert_bod_email' => ['nullable', 'email', 'max:255'],
            'alert_it_lead_delay_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'alert_bod_delay_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'alert_customer_delay_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'pc_audit_api_base_url' => ['required', 'url:http,https', 'max:500'],
            'pc_audit_tool_version' => ['required', 'string', 'max:50'],
            'pc_audit_minimum_tool_version' => ['required', 'string', 'max:50'],
            'pc_audit_enabled' => ['required', 'boolean'],
            'pc_audit_download_url' => ['nullable', 'url:http,https', 'max:1000'],
            'pc_audit_disabled_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $values = [
            'mail.mailer' => $data['mail_mailer'], 'mail.host' => $data['mail_host'] ?? '', 'mail.port' => (string) $data['mail_port'],
            'mail.encryption' => $data['mail_encryption'], 'mail.username' => $data['mail_username'] ?? '',
            'mail.from_address' => $data['mail_from_address'] ?? '', 'mail.from_name' => $data['mail_from_name'],
            'system.notification_email' => $data['notification_email'],
            'security.allow_self_registration' => $data['allow_self_registration'] ? '1' : '0',
            'password_reset.otp_expire_minutes' => (string) $data['otp_expire_minutes'],
            'password_reset.link_expire_minutes' => (string) $data['link_expire_minutes'],
            'password_reset.max_otp_attempts' => (string) $data['max_otp_attempts'],
            'security.login_max_attempts' => (string) $data['login_max_attempts'],
            'security.login_lockout_minutes' => (string) $data['login_lockout_minutes'],
            'alert_email.enabled' => $data['alert_email_enabled'] ? '1' : '0',
            'alert_email.operator_enabled' => $data['alert_operator_enabled'] ? '1' : '0',
            'alert_email.it_lead_enabled' => $data['alert_it_lead_enabled'] ? '1' : '0',
            'alert_email.bod_enabled' => $data['alert_bod_enabled'] ? '1' : '0',
            'alert_email.customer_enabled' => $data['alert_customer_enabled'] ? '1' : '0',
            'alert_email.send_resolution' => $data['alert_send_resolution'] ? '1' : '0',
            'alert_email.it_lead_email' => $data['alert_it_lead_email'] ?? '',
            'alert_email.bod_email' => $data['alert_bod_email'] ?? '',
            'alert_email.it_lead_delay_minutes' => (string) $data['alert_it_lead_delay_minutes'],
            'alert_email.bod_delay_minutes' => (string) $data['alert_bod_delay_minutes'],
            'alert_email.customer_delay_minutes' => (string) $data['alert_customer_delay_minutes'],
        ];
        if (filled($data['mail_password'] ?? null)) $values['mail.password'] = $data['mail_password'];

        foreach ($values as $key => $value) {
            [$group, $shortKey] = array_pad(explode('.', $key, 2), 2, 'system');
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group, 'label' => ucwords(str_replace(['.', '_'], [' / ', ' '], $shortKey))]);
        }

        PcAuditSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'api_base_url' => rtrim($data['pc_audit_api_base_url'], '/'),
                'tool_version' => $data['pc_audit_tool_version'],
                'minimum_tool_version' => $data['pc_audit_minimum_tool_version'],
                'enabled' => $data['pc_audit_enabled'],
                'download_url' => $data['pc_audit_download_url'] ?? null,
                'disabled_message' => $data['pc_audit_disabled_message'] ?? null,
            ]
        );

        return back()->with('success', 'System settings and PC Audit configuration saved successfully.');
    }

    public function testMail(Request $request): mixed
    {
        $user = $request->user();
        if (!$user?->email) return back()->withErrors(['mail' => 'The current administrator does not have an email address.']);
        if (SystemSetting::value('mail.mailer', 'log') !== 'smtp') return back()->withErrors(['mail' => 'Select SMTP and save the settings before testing email delivery.']);

        Mail::raw('This is a test email from KPI Dashboard System Settings. SMTP configuration is working.', function ($message) use ($user) {
            $message->to($user->email)->subject('KPI Dashboard - SMTP Test');
        });

        return back()->with('success', 'Test email sent to '.$user->email.'.');
    }
}
