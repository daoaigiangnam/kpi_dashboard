<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserGroup;
use App\Notifications\RegistrationEmailVerificationNotification;
use App\Notifications\UserRegistrationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function show(Request $request): mixed
    {
        if (!$this->registrationEnabled()) {
            return redirect()->route('login');
        }

        $this->refreshCaptcha($request);
        return view('auth.register', [
            'captchaTarget' => $request->session()->get('registration_captcha.target', 50),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::where('is_active', true)->orderBy('name')->get(),
            'jobTitles' => JobTitle::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function register(Request $request): mixed
    {
        if (!$this->registrationEnabled()) {
            return redirect()->route('login');
        }

        $credentials = $request->validate([
            'employee_code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'employee_code')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:Male,Female,Other'],
            'join_date' => ['nullable', 'date'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'job_title_id' => ['nullable', 'exists:job_titles,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'captcha_position' => ['required', 'numeric', 'between:0,100'],
        ]);

        $challenge = $request->session()->pull('registration_captcha');
        $expected = (float) ($challenge['target'] ?? -1);
        $position = (float) $credentials['captcha_position'];

        if ($expected < 0 || abs($position - $expected) > 8) {
            $this->refreshCaptcha($request);
            return back()->withErrors(['captcha_position' => 'Slider verification failed. Please try the new challenge.'])->withInput($request->except(['password', 'password_confirmation']));
        }

        $notificationEmail = trim((string) SystemSetting::value('system.notification_email', ''));
        if (!filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
            $this->refreshCaptcha($request);
            return back()->withErrors(['email' => 'Registration is temporarily unavailable because the system notification email has not been configured.'])->withInput($request->except(['password', 'password_confirmation']));
        }

        $expireMinutes = (int) SystemSetting::value('password_reset.otp_expire_minutes', '10');
        $otp = (string) random_int(100000, 999999);

        DB::table('registration_email_verifications')->updateOrInsert(
            ['email' => $credentials['email']],
            [
                'payload' => json_encode(collect($credentials)->except(['password', 'captcha_position'])->all(), JSON_THROW_ON_ERROR),
                'password_hash' => Hash::make($credentials['password']),
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes($expireMinutes),
                'attempts' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $verificationId = DB::table('registration_email_verifications')->where('email', $credentials['email'])->value('id');

        try {
            Notification::route('mail', $credentials['email'])->notify(new RegistrationEmailVerificationNotification($otp, $expireMinutes));
        } catch (\Throwable $e) {
            DB::table('registration_email_verifications')->where('id', $verificationId)->delete();
            Log::error('Unable to send registration email verification OTP.', ['email' => $credentials['email'], 'exception' => $e->getMessage()]);
            $this->refreshCaptcha($request);
            return back()->withErrors(['email' => 'We could not send the verification email. Please check your email address or contact the administrator.'])->withInput($request->except(['password', 'password_confirmation']));
        }

        $request->session()->put('registration_verification_id', $verificationId);
        return redirect()->route('register.verify')->with('status', 'A verification OTP has been sent to your email address.');
    }

    public function showVerify(Request $request): mixed
    {
        $verification = $this->currentVerification($request);
        if (!$verification) return redirect()->route('register')->withErrors(['email' => 'Your registration verification session has expired. Please register again.']);
        return view('auth.verify-registration', ['email' => $verification->email, 'expiresAt' => $verification->expires_at]);
    }

    public function verify(Request $request): mixed
    {
        $request->validate(['otp' => ['required', 'digits:6']]);
        $verification = $this->currentVerification($request);
        if (!$verification) return redirect()->route('register')->withErrors(['email' => 'Your registration verification session has expired. Please register again.']);

        $maxAttempts = (int) SystemSetting::value('password_reset.max_otp_attempts', '5');
        if (now()->greaterThan($verification->expires_at)) {
            DB::table('registration_email_verifications')->where('id', $verification->id)->delete();
            $request->session()->forget('registration_verification_id');
            return redirect()->route('register')->withErrors(['email' => 'The verification OTP has expired. Please register again.']);
        }
        if ((int) $verification->attempts >= $maxAttempts) {
            DB::table('registration_email_verifications')->where('id', $verification->id)->delete();
            $request->session()->forget('registration_verification_id');
            return redirect()->route('register')->withErrors(['email' => 'Too many invalid OTP attempts. Please register again.']);
        }
        if (!Hash::check($request->string('otp')->toString(), $verification->otp_hash)) {
            DB::table('registration_email_verifications')->where('id', $verification->id)->increment('attempts');
            return back()->withErrors(['otp' => 'The verification OTP is invalid. Please check your email and try again.']);
        }

        $payload = json_decode($verification->payload, true, 512, JSON_THROW_ON_ERROR);
        if (User::withTrashed()->where('email', $payload['email'])->exists()) {
            DB::table('registration_email_verifications')->where('id', $verification->id)->delete();
            $request->session()->forget('registration_verification_id');
            return redirect()->route('register')->withErrors(['email' => 'This email address is already registered in the system.']);
        }
        if (User::withTrashed()->where('employee_code', $payload['employee_code'])->exists()) {
            DB::table('registration_email_verifications')->where('id', $verification->id)->delete();
            $request->session()->forget('registration_verification_id');
            return redirect()->route('register')->withErrors(['employee_code' => 'This employee code is already registered in the system.']);
        }

        $viewerGroup = UserGroup::where('name', 'KPI Viewer')->first();
        if (!$viewerGroup) {
            Log::error('KPI Viewer group is missing; self-registration cannot complete.', ['email' => $payload['email']]);
            return redirect()->route('register')->withErrors(['email' => 'Registration is temporarily unavailable. Please contact the administrator.']);
        }

        $user = DB::transaction(function () use ($payload, $verification, $viewerGroup) {
            return User::create([
                'employee_code' => $payload['employee_code'], 'name' => $payload['name'], 'email' => $payload['email'],
                'phone' => $payload['phone'] ?? null, 'date_of_birth' => $payload['date_of_birth'] ?? null, 'gender' => $payload['gender'] ?? null,
                'join_date' => $payload['join_date'] ?? null, 'department_id' => $payload['department_id'] ?? null, 'unit_id' => $payload['unit_id'] ?? null,
                'job_title_id' => $payload['job_title_id'] ?? null, 'notes' => $payload['notes'] ?? null, 'password' => $verification->password_hash,
                'user_group_id' => $viewerGroup->id, 'is_active' => false, 'registration_status' => 'pending',
            ]);
        });

        DB::table('registration_email_verifications')->where('id', $verification->id)->delete();
        $request->session()->forget('registration_verification_id');

        $notificationEmail = trim((string) SystemSetting::value('system.notification_email', ''));
        if (filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                Notification::route('mail', $notificationEmail)->notify(new UserRegistrationNotification($user));
            } catch (\Throwable $e) {
                Log::error('Unable to send new user registration notification.', ['user_id' => $user->id, 'notification_email' => $notificationEmail, 'exception' => $e->getMessage()]);
            }
        } else {
            Log::warning('New user registration created without admin notification email configured.', ['user_id' => $user->id]);
        }

        return redirect()->route('login')->with('status', 'Email verified successfully. Your registration is now pending Super Admin approval. You will be able to sign in after approval.');
    }

    public function resendVerify(Request $request): mixed
    {
        $verification = $this->currentVerification($request);
        if (!$verification) return redirect()->route('register')->withErrors(['email' => 'Your registration verification session has expired. Please register again.']);

        $expireMinutes = (int) SystemSetting::value('password_reset.otp_expire_minutes', '10');
        $otp = (string) random_int(100000, 999999);
        DB::table('registration_email_verifications')->where('id', $verification->id)->update(['otp_hash' => Hash::make($otp), 'expires_at' => now()->addMinutes($expireMinutes), 'attempts' => 0, 'updated_at' => now()]);
        try {
            Notification::route('mail', $verification->email)->notify(new RegistrationEmailVerificationNotification($otp, $expireMinutes));
        } catch (\Throwable $e) {
            Log::error('Unable to resend registration email verification OTP.', ['email' => $verification->email, 'exception' => $e->getMessage()]);
            return back()->withErrors(['otp' => 'We could not resend the verification email. Please try again later.']);
        }
        return back()->with('status', 'A new verification OTP has been sent to your email address.');
    }

    private function currentVerification(Request $request): ?object
    {
        $id = $request->session()->get('registration_verification_id');
        if (!$id) return null;
        return DB::table('registration_email_verifications')->where('id', $id)->first();
    }

    private function registrationEnabled(): bool
    {
        return SystemSetting::value('security.allow_self_registration', '1') === '1';
    }

    private function refreshCaptcha(Request $request): void
    {
        $request->session()->put('registration_captcha', ['target' => random_int(20, 80), 'created_at' => now()->timestamp]);
    }
}
