<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function show(Request $request): mixed
    {
        $this->refreshCaptcha($request);

        return view('auth.login', [
            'captchaTarget' => $request->session()->get('login_captcha.target', 50),
        ]);
    }

    public function login(Request $request): mixed
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'captcha_position' => ['required', 'numeric', 'between:0,100'],
        ]);

        $challenge = $request->session()->pull('login_captcha');
        $expected = (float) ($challenge['target'] ?? -1);
        $position = (float) $credentials['captcha_position'];

        if ($expected < 0 || abs($position - $expected) > 8) {
            $this->refreshCaptcha($request);

            return back()
                ->withErrors(['captcha_position' => 'Slider verification failed. Please try the new challenge.'])
                ->onlyInput('email');
        }

        $maxAttempts = (int) SystemSetting::value('security.login_max_attempts', '5');
        $lockoutMinutes = (int) SystemSetting::value('security.login_lockout_minutes', '15');
        $key = $this->loginSecurityKey($request, $credentials['email']);
        $attemptKey = $key.':attempts';
        $lockKey = $key.':lock';

        if (Cache::has($lockKey)) {
            $this->refreshCaptcha($request);

            return back()
                ->withErrors(['email' => "Too many failed login attempts. Please try again in {$lockoutMinutes} minutes."])
                ->onlyInput('email');
        }

        $remember = $request->boolean('remember');

        if (Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $remember)) {
            Cache::forget($attemptKey);
            Cache::forget($lockKey);
            config(['session.expire_on_close' => ! $remember]);
            $request->session()->regenerate();

            return redirect()->intended('/admin');
        }

        Cache::add($attemptKey, 0, now()->addMinutes($lockoutMinutes));
        $attempts = (int) Cache::increment($attemptKey);

        $this->refreshCaptcha($request);

        if ($attempts >= $maxAttempts) {
            Cache::forget($attemptKey);
            Cache::put($lockKey, true, now()->addMinutes($lockoutMinutes));

            return back()
                ->withErrors(['email' => "Too many failed login attempts. Login is locked for {$lockoutMinutes} minutes."])
                ->onlyInput('email');
        }

        $remaining = max(0, $maxAttempts - $attempts);

        return back()
            ->withErrors(['email' => "Invalid credentials or inactive account. {$remaining} attempt(s) remaining before temporary lockout."])
            ->onlyInput('email');
    }

    public function logout(Request $request): mixed
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function refreshCaptcha(Request $request): void
    {
        $request->session()->put('login_captcha', [
            'target' => random_int(20, 80),
            'created_at' => now()->timestamp,
        ]);
    }

    private function loginSecurityKey(Request $request, string $email): string
    {
        return 'login-security:'.hash('sha256', Str::lower(trim($email)).'|'.$request->ip());
    }
}
