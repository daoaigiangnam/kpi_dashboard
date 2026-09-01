<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SystemSettingController extends Controller
{
    private const DEFAULTS = [
        'mail.mailer' => 'log',
        'mail.host' => '',
        'mail.port' => '587',
        'mail.encryption' => 'tls',
        'mail.username' => '',
        'mail.password' => '',
        'mail.from_address' => '',
        'mail.from_name' => 'KPI Dashboard',
        'password_reset.otp_expire_minutes' => '10',
        'password_reset.link_expire_minutes' => '60',
        'password_reset.max_otp_attempts' => '5',
    ];

    public function index(): mixed
    {
        $settings = collect(self::DEFAULTS)->mapWithKeys(function ($default, $key) {
            return [$key => SystemSetting::value($key, $default)];
        });

        return view('admin.settings.index', [
            'settings' => $settings,
            'mailPasswordConfigured' => filled(SystemSetting::value('mail.password')),
        ]);
    }

    public function update(Request $request): mixed
    {
        $data = $request->validate([
            'mail_mailer' => ['required', 'in:log,smtp'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mail_encryption' => ['required', 'in:none,tls,ssl'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:1000'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:150'],
            'otp_expire_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'link_expire_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'max_otp_attempts' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $values = [
            'mail.mailer' => $data['mail_mailer'],
            'mail.host' => $data['mail_host'] ?? '',
            'mail.port' => (string) $data['mail_port'],
            'mail.encryption' => $data['mail_encryption'],
            'mail.username' => $data['mail_username'] ?? '',
            'mail.from_address' => $data['mail_from_address'] ?? '',
            'mail.from_name' => $data['mail_from_name'],
            'password_reset.otp_expire_minutes' => (string) $data['otp_expire_minutes'],
            'password_reset.link_expire_minutes' => (string) $data['link_expire_minutes'],
            'password_reset.max_otp_attempts' => (string) $data['max_otp_attempts'],
        ];

        if (filled($data['mail_password'] ?? null)) {
            $values['mail.password'] = $data['mail_password'];
        }

        foreach ($values as $key => $value) {
            [$group, $shortKey] = array_pad(explode('.', $key, 2), 2, 'system');
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $group, 'label' => ucwords(str_replace(['.', '_'], [' / ', ' '], $shortKey))]
            );
        }

        Cache::forget('system_settings.mail');

        return back()->with('success', 'System settings saved. Mail credentials are stored encrypted in the database.');
    }
}
