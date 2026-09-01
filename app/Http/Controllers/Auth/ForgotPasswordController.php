<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function show(): mixed
    {
        return view('auth.forgot-password');
    }

    public function send(Request $request): mixed
    {
        $credentials = $request->validate(['email' => ['required', 'email']]);

        $user = User::query()
            ->where('email', $credentials['email'])
            ->where('is_active', true)
            ->first();

        // Do not reveal whether an email exists in the system.
        if (!$user) {
            return back()->with('status', 'If the account exists, a password reset email has been sent.');
        }

        $token = Password::broker()->createToken($user);
        $otp = (string) random_int(100000, 999999);

        DB::table('password_reset_otps')->where('email', $user->email)->delete();
        DB::table('password_reset_otps')->insert([
            'email' => $user->email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->notify(new PasswordResetOtpNotification($token, $otp));

        return back()->with('status', 'A password reset email with an OTP and secure reset link has been sent.');
    }
}
