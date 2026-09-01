<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller; use Illuminate\Http\Request; use Illuminate\Support\Facades\Auth;
class LoginController extends Controller { public function show(){return view('auth.login');} public function login(Request $r){$c=$r->validate(['email'=>'required|email','password'=>'required|string']); if(Auth::attempt(['email'=>$c['email'],'password'=>$c['password'],'is_active'=>true],$r->boolean('remember'))){$r->session()->regenerate();return redirect()->intended('/admin');}return back()->withErrors(['email'=>'Invalid credentials or inactive account.'])->onlyInput('email');} public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect()->route('login');} }
