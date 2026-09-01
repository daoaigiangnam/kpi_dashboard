<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Change Password - KPI Dashboard</title>
    <style>
        *{box-sizing:border-box}body{font-family:Inter,Arial,sans-serif;background:#f4f8f5;display:grid;place-items:center;min-height:100vh;margin:0;color:#172033}.box{background:#fff;width:min(440px,calc(100% - 32px));padding:30px;border-radius:14px;box-shadow:0 12px 35px #17321f14;border:1px solid #e1e9e3}h2{margin:0 0 8px}p{color:#64748b;line-height:1.5}.input{width:100%;padding:12px;border:1px solid #cbd8cf;border-radius:8px;margin:7px 0 15px}.btn{width:100%;padding:12px;border:0;border-radius:8px;background:#238b57;color:#fff;font-weight:700;cursor:pointer}.error{padding:11px;border-radius:8px;margin-bottom:15px;background:#fee2e2;color:#991b1b}.hint{font-size:13px;color:#64748b;margin-top:-7px;margin-bottom:15px}.otp{letter-spacing:5px;text-align:center;font-size:20px;font-weight:700}.password-wrap{position:relative}.password-wrap .input{padding-right:82px}.show{position:absolute;right:9px;top:9px;border:0;background:transparent;color:#23784e;font-weight:700;cursor:pointer;padding:6px}.back{display:block;text-align:center;margin-top:16px;color:#23784e;text-decoration:none}
    </style>
</head>
<body>
<div class="box">
    <h2>Change Password</h2>
    <p>Enter the 6-digit OTP from your email and choose a new password.</p>

    @if($errors->any())
        <div class="error"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <label for="email">Email / Login</label>
        <input class="input" id="email" type="email" name="email" value="{{ old('email',$email) }}" required autocomplete="email">

        <label for="otp">OTP Token</label>
        <input class="input otp" id="otp" type="text" name="otp" value="{{ old('otp') }}" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code">
        <div class="hint">OTP is valid for 10 minutes. The reset link is valid for 60 minutes.</div>

        <label for="password">New Password</label>
        <div class="password-wrap">
            <input class="input" id="password" type="password" name="password" minlength="8" required autocomplete="new-password">
            <button class="show" type="button" onclick="togglePassword('password',this)">Show</button>
        </div>

        <label for="password_confirmation">Confirm Password</label>
        <div class="password-wrap">
            <input class="input" id="password_confirmation" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password">
            <button class="show" type="button" onclick="togglePassword('password_confirmation',this)">Show</button>
        </div>

        <button class="btn" type="submit">Change Password</button>
    </form>

    <a class="back" href="{{ route('login') }}">← Back to Sign in</a>
</div>
<script>
function togglePassword(id, button){const input=document.getElementById(id);const show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';}
</script>
</body>
</html>
