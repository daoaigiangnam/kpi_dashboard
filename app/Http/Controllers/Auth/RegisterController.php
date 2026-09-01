<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\UserRegistrationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function show(Request $request): mixed
    {
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

            return back()
                ->withErrors(['captcha_position' => 'Slider verification failed. Please try the new challenge.'])
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        $notificationEmail = trim((string) SystemSetting::value('system.notification_email', ''));
        if (!filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
            $this->refreshCaptcha($request);

            return back()
                ->withErrors(['email' => 'Registration is temporarily unavailable because the system notification email has not been configured.'])
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        $user = User::create([
            'employee_code' => $credentials['employee_code'],
            'name' => $credentials['name'],
            'email' => $credentials['email'],
            'phone' => $credentials['phone'] ?? null,
            'date_of_birth' => $credentials['date_of_birth'] ?? null,
            'gender' => $credentials['gender'] ?? null,
            'join_date' => $credentials['join_date'] ?? null,
            'department_id' => $credentials['department_id'] ?? null,
            'unit_id' => $credentials['unit_id'] ?? null,
            'job_title_id' => $credentials['job_title_id'] ?? null,
            'notes' => $credentials['notes'] ?? null,
            'password' => $credentials['password'],
            'is_active' => false,
            'registration_status' => 'pending',
        ]);

        Notification::route('mail', $notificationEmail)->notify(new UserRegistrationNotification($user));

        return redirect()->route('login')->with('status', 'Registration submitted successfully. Your account is pending Super Admin approval. You will be able to sign in after approval.');
    }

    private function refreshCaptcha(Request $request): void
    {
        $request->session()->put('registration_captcha', [
            'target' => random_int(20, 80),
            'created_at' => now()->timestamp,
        ]);
    }
}
