<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>KPI Dashboard System — Verify Email</title>
    <style>
        *{box-sizing:border-box}:root{--navy:#0f172a;--muted:#64748b;--line:#cbd5e1;--blue:#2563eb;--blue-dark:#1d4ed8}
        body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:linear-gradient(135deg,#b8c9df 0%,#d4e0ed 42%,#c6d8e9 100%);min-height:100vh;margin:0;color:var(--navy);padding:38px 16px;display:grid;place-items:center;position:relative;overflow-x:hidden}
        body:before,body:after{content:"";position:fixed;border-radius:50%;pointer-events:none}body:before{width:700px;height:700px;top:-420px;left:-260px;background:radial-gradient(circle,rgba(37,99,235,.28),rgba(37,99,235,0) 70%)}body:after{width:680px;height:680px;bottom:-430px;right:-250px;background:radial-gradient(circle,rgba(14,165,233,.22),rgba(14,165,233,0) 70%)}
        .box{position:relative;z-index:1;width:min(470px,100%);background:rgba(255,255,255,.98);border:1px solid rgba(255,255,255,.9);border-radius:20px;padding:30px;box-shadow:0 30px 80px rgba(15,23,42,.22),0 8px 24px rgba(15,23,42,.10)}
        .brand{display:flex;align-items:center;gap:10px;margin-bottom:26px}.mark{width:38px;height:38px;border-radius:11px;background:linear-gradient(145deg,#3b82f6,#1d4ed8);color:#fff;display:grid;place-items:center;font-weight:800;box-shadow:0 8px 18px rgba(37,99,235,.3)}.brand-name{font-size:17px;font-weight:750}
        h1{font-size:25px;margin:0 0 7px;letter-spacing:-.5px}.subtitle{color:var(--muted);margin:0 0 22px;font-size:14px;line-height:1.55}.email{font-weight:700;color:#1e40af;word-break:break-all}.status{padding:11px 12px;border-radius:9px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;margin-bottom:16px;font-size:13px}.error{padding:11px 12px;border-radius:9px;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;margin-bottom:16px;font-size:13px}.label{display:block;margin-bottom:7px;font-size:13px;font-weight:700;color:#334155}.otp{width:100%;height:54px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:#fff;font:inherit;font-size:24px;letter-spacing:8px;text-align:center;color:var(--navy);outline:none}.otp:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.12)}.hint{font-size:12px;color:var(--muted);margin:9px 0 0}.button{width:100%;height:44px;margin-top:18px;border:0;border-radius:9px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;font-weight:700;cursor:pointer;box-shadow:0 8px 18px rgba(37,99,235,.22)}.secondary{width:100%;height:42px;margin-top:10px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#334155;font-weight:650;cursor:pointer}.secondary:hover{background:#f8fafc}.back{display:block;text-align:center;margin-top:16px;color:#2563eb;text-decoration:none;font-size:13px;font-weight:600}.note{font-size:12px;color:var(--muted);line-height:1.5;margin:18px 0 0;padding-top:15px;border-top:1px solid #e2e8f0}
        @media(max-width:520px){body{padding:18px 10px}.box{padding:22px;border-radius:16px}h1{font-size:23px}}
    </style>
</head>
<body>
<div class="box">
    <div class="brand"><div class="mark">K</div><div class="brand-name">KPI Dashboard System</div></div>
    <h1>Verify your email</h1>
    <p class="subtitle">We sent a 6-digit verification code to <span class="email">{{ $email }}</span>. Verify this email before your registration is sent to the Super Admin approval queue.</p>

    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

    <form method="post" action="{{ route('register.verify.submit') }}">
        @csrf
        <label class="label" for="otp">Email Verification OTP</label>
        <input class="otp" id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
        <p class="hint">The code expires at {{ \Illuminate\Support\Carbon::parse($expiresAt)->format('Y-m-d H:i') }}.</p>
        <button class="button" type="submit">Verify Email &amp; Submit Registration</button>
    </form>

    <form method="post" action="{{ route('register.verify.resend') }}">
        @csrf
        <button class="secondary" type="submit">Send New OTP</button>
    </form>

    <p class="note">Your account is not created until the OTP is verified. After successful verification, the registration will remain inactive until a Super Admin approves it.</p>
    <a class="back" href="{{ route('login') }}">← Back to Sign in</a>
</div>
</body>
</html>
