<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>KPI Dashboard Login</title>
    <style>
        *{box-sizing:border-box}body{font-family:Inter,Arial,sans-serif;background:#f4f8f5;display:grid;place-items:center;min-height:100vh;margin:0;color:#172033}.box{background:#fff;width:min(390px,calc(100% - 32px));padding:30px;border-radius:14px;box-shadow:0 12px 35px #17321f14;border:1px solid #e1e9e3}.subtitle{color:#64748b;margin:0 0 22px}.field{margin-bottom:16px}.label{display:block;margin-bottom:6px;font-weight:600}.password-wrap{position:relative}.input{width:100%;padding:12px;border:1px solid #cbd8cf;border-radius:8px}.password-wrap .input{padding-right:72px}.show{position:absolute;right:7px;top:6px;border:0;background:transparent;color:#23784e;font-weight:700;cursor:pointer;padding:6px}.remember{display:flex;align-items:center;gap:7px;margin:4px 0 18px;font-size:14px}.remember input{width:auto;margin:0}.captcha-box{padding:12px;background:#f8faf9;border:1px solid #d7e3db;border-radius:8px;margin-bottom:16px}.captcha-label{display:block;font-weight:600;margin-bottom:8px}.captcha-question{font-weight:700;letter-spacing:.4px;margin-bottom:8px}.button{width:100%;padding:12px;border:0;border-radius:8px;background:#238b57;color:#fff;font-weight:700;cursor:pointer}.button:hover{background:#197247}.forgot{display:block;text-align:center;margin-top:17px;color:#23784e;text-decoration:none;font-size:14px}.error,.status{padding:11px;border-radius:8px;margin-bottom:15px}.error{background:#fee2e2;color:#991b1b}.status{background:#dcfce7;color:#166534}.security-note{font-size:12px;color:#64748b;margin:-7px 0 16px}
    </style>
</head>
<body>
<div class="box">
    <h2>KPI Dashboard</h2>
    <p class="subtitle">Admin Sign In</p>

    @if(session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('login.attempt') }}">
        @csrf
        <div class="field">
            <label class="label" for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>

        <div class="field">
            <label class="label" for="password">Password</label>
            <div class="password-wrap">
                <input class="input" id="password" type="password" name="password" required autocomplete="current-password">
                <button class="show" type="button" onclick="togglePassword()">Show</button>
            </div>
        </div>

        <div class="captcha-box">
            <label class="captcha-label" for="captcha_answer">CAPTCHA</label>
            <div class="captcha-question">{{ $captchaQuestion }}</div>
            <input class="input" id="captcha_answer" type="text" name="captcha_answer" inputmode="numeric" pattern="[0-9]{1,3}" maxlength="3" required autocomplete="off" placeholder="Enter the answer">
        </div>
        <p class="security-note">Anti-bot protection is required before authentication.</p>

        <label class="remember"><input type="checkbox" name="remember" value="1"> Remember me</label>
        <button class="button" type="submit">Sign in</button>
    </form>

    <a class="forgot" href="{{ route('password.request') }}">Forgot Password?</a>
</div>
<script>
function togglePassword(){const input=document.getElementById('password');const button=document.querySelector('.show');const show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';}
</script>
</body>
</html>
