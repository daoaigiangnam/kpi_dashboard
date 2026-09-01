<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Forgot Password - KPI Dashboard</title>
    <style>
        *{box-sizing:border-box}body{font-family:Inter,Arial,sans-serif;background:#f4f8f5;display:grid;place-items:center;min-height:100vh;margin:0;color:#172033}.box{background:#fff;width:min(420px,calc(100% - 32px));padding:30px;border-radius:14px;box-shadow:0 12px 35px #17321f14;border:1px solid #e1e9e3}h2{margin:0 0 8px}p{color:#64748b;line-height:1.5}.input{width:100%;padding:12px;border:1px solid #cbd8cf;border-radius:8px;margin:7px 0 16px}.btn{width:100%;padding:12px;border:0;border-radius:8px;background:#238b57;color:#fff;font-weight:700;cursor:pointer}.link{display:block;text-align:center;margin-top:16px;color:#23784e;text-decoration:none}.error,.status{padding:11px;border-radius:8px;margin-bottom:15px}.error{background:#fee2e2;color:#991b1b}.status{background:#dcfce7;color:#166534}
    </style>
</head>
<body>
<div class="box">
    <h2>Forgot Password</h2>
    <p>Enter your account email. We will send a one-time OTP and a secure link to change your password.</p>

    @if(session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('password.email') }}">
        @csrf
        <label for="email">Email / Login</label>
        <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
        <button class="btn" type="submit">Send OTP & Reset Link</button>
    </form>

    <a class="link" href="{{ route('login') }}">← Back to Sign in</a>
</div>
</body>
</html>
