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
            'captchaQuestion' => $request->session()->get('login_captcha.question'),
        ]);
    }

    public function login(Request $request): mixed
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'captcha_answer' => ['required', 'digits_between:1,3'],
        ]);

        $expected = (string) $request->session()->pull('login_captcha.answer', '');

        if ($expected === '' || ! hash_equals($expected, (string) $credentials['captcha_answer'])) {
            $this->refreshCaptcha($request);

            return back()
                ->withErrors(['captcha_answer' => 'CAPTCHA is incorrect. Please solve the new CAPTCHA and try again.'])
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

        if (Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'))) {
            Cache::forget($attemptKey);
            Cache::forget($lockKey);
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
        $left = random_int(10, 49);
        $right = random_int(1, 49);

        $request->session()->put('login_captcha', [
            'question' => "What is {$left} + {$right}?",
            'answer' => (string) ($left + $right),
        ]);
    }

    private function loginSecurityKey(Request $request, string $email): string
    {
        return 'login-security:'.hash('sha256', Str::lower(trim($email)).'|'.$request->ip());
    }
}
