<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function index()
    {
        return view('account.index', ['user' => Auth::user()]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:Male,Female,Other'],
        ]);

        $user->fill($data)->save();

        return back()->with('success', 'Your account information has been updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $credentials = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $user = Auth::user();

        if (!Hash::check($credentials['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        if (Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['password' => 'The new password must be different from your current password.']);
        }

        $user->forceFill(['password' => $credentials['password']])->save();

        return back()->with('success', 'Your password has been changed successfully.');
    }
}
