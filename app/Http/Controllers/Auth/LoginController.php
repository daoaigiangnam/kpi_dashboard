<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        if (Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended('/admin');
        }

        $this->refreshCaptcha($request);

        return back()
            ->withErrors(['email' => 'Invalid credentials or inactive account.'])
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
}
