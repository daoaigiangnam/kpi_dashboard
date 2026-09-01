<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    public function show(Request $request): mixed
    {
        return view('auth.reset-password', [
            'token' => (string) $request->query('token', ''),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): mixed
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $otpRecord = DB::table('password_reset_otps')
            ->where('email', $credentials['email'])
            ->latest('id')
            ->first();

        if (!$otpRecord || now()->greaterThan($otpRecord->expires_at)) {
            return back()->withErrors(['otp' => 'The OTP has expired. Please request a new password reset email.'])->withInput($request->except('password', 'password_confirmation'));
        }

        if ((int) $otpRecord->attempts >= 5) {
            return back()->withErrors(['otp' => 'Too many invalid OTP attempts. Please request a new password reset email.'])->withInput($request->except('password', 'password_confirmation'));
        }

        if (!Hash::check($credentials['otp'], $otpRecord->otp_hash)) {
            DB::table('password_reset_otps')->where('id', $otpRecord->id)->increment('attempts');
            return back()->withErrors(['otp' => 'The OTP is invalid. Please check the email and try again.'])->withInput($request->except('password', 'password_confirmation'));
        }

        $status = Password::broker()->reset(
            [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
                'password_confirmation' => $credentials['password_confirmation'],
                'token' => $credentials['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'This password reset link is invalid or has expired. Please request a new one.']);
        }

        DB::table('password_reset_otps')->where('id', $otpRecord->id)->delete();

        return redirect()->route('login')->with('status', 'Your password has been changed successfully. You can now sign in with the new password.');
    }
}
