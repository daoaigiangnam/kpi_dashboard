<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>KPI Dashboard System — Sign up</title>
    <style>
        *{box-sizing:border-box}:root{--navy:#0f172a;--muted:#64748b;--line:#cbd5e1;--blue:#2563eb;--blue-dark:#1d4ed8}
        body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:linear-gradient(135deg,#b8c9df 0%,#d4e0ed 42%,#c6d8e9 100%);min-height:100vh;margin:0;color:var(--navy);padding:38px 16px;position:relative;overflow-x:hidden}
        body:before,body:after{content:"";position:fixed;border-radius:50%;pointer-events:none}.before{}
        body:before{width:700px;height:700px;top:-420px;left:-260px;background:radial-gradient(circle,rgba(37,99,235,.28),rgba(37,99,235,0) 70%)}body:after{width:680px;height:680px;bottom:-430px;right:-250px;background:radial-gradient(circle,rgba(14,165,233,.22),rgba(14,165,233,0) 70%)}
        .box{position:relative;z-index:1;width:min(760px,100%);margin:0 auto;background:rgba(255,255,255,.98);border:1px solid rgba(255,255,255,.9);border-radius:20px;padding:30px;box-shadow:0 30px 80px rgba(15,23,42,.22),0 8px 24px rgba(15,23,42,.10)}
        .brand{display:flex;align-items:center;gap:10px;margin-bottom:24px}.mark{width:38px;height:38px;border-radius:11px;background:linear-gradient(145deg,#3b82f6,#1d4ed8);color:#fff;display:grid;place-items:center;font-weight:800;box-shadow:0 8px 18px rgba(37,99,235,.3)}.brand-name{font-size:17px;font-weight:750}
        h1{font-size:25px;margin:0 0 7px;letter-spacing:-.5px}.subtitle{color:var(--muted);margin:0 0 24px;font-size:14px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.field{margin-bottom:1px}.label{display:block;margin-bottom:6px;font-size:13px;font-weight:650;color:#334155}.input{width:100%;height:43px;padding:10px 11px;border:1px solid var(--line);border-radius:9px;background:#fff;font:inherit;font-size:14px;color:var(--navy);outline:none}.input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.12)}textarea.input{height:auto;resize:vertical}
        .section{border:1px solid #dbe4ef;background:#f8fbff;border-radius:13px;padding:17px;margin-top:18px}.section h3{margin:0 0 14px;font-size:14px}.captcha-box{margin-top:18px;padding:14px;border:1px solid #cfdae7;background:linear-gradient(145deg,#f8fbff,#eef4fb);border-radius:12px}.captcha-label{display:block;font-size:13px;font-weight:700;color:#334155;margin-bottom:9px}.slider-track{position:relative;height:44px;border-radius:22px;background:linear-gradient(180deg,#e7eef7,#dce6f1);border:1px solid #c4d1df;box-shadow:inset 0 2px 5px rgba(15,23,42,.08);touch-action:none;user-select:none;overflow:hidden}.slider-fill{position:absolute;left:0;top:0;height:100%;width:0;background:linear-gradient(90deg,#bfdbfe,#93c5fd);pointer-events:none}.slider-target{position:absolute;top:4px;width:34px;height:34px;border-radius:50%;border:2px dashed #7c91aa;background:rgba(255,255,255,.75);transform:translateX(-50%);pointer-events:none}.slider-thumb{position:absolute;top:4px;left:4px;width:34px;height:34px;border-radius:50%;background:linear-gradient(145deg,#3b82f6,#1d4ed8);border:2px solid #fff;color:#fff;display:grid;place-items:center;font-size:19px;font-weight:800;box-shadow:0 5px 12px rgba(37,99,235,.34);cursor:grab;z-index:2}.slider-thumb.dragging{cursor:grabbing}.captcha-success{display:none;text-align:center;color:#166534;font-size:12px;font-weight:700;padding-top:7px}.captcha-box.verified .captcha-success{display:block}.captcha-box.verified .slider-track{border-color:#86efac;background:#ecfdf5}.captcha-box.verified .slider-fill{background:linear-gradient(90deg,#86efac,#4ade80)}.captcha-box.verified .slider-thumb{background:linear-gradient(145deg,#22c55e,#16a34a);cursor:default}.hint{font-size:11px;color:var(--muted);margin:7px 0 0}.error{padding:11px 12px;border-radius:9px;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;margin-bottom:16px;font-size:13px}.button{width:100%;height:44px;margin-top:18px;border:0;border-radius:9px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;font-weight:700;cursor:pointer;box-shadow:0 8px 18px rgba(37,99,235,.22)}.back{display:block;text-align:center;margin-top:15px;color:#2563eb;text-decoration:none;font-size:13px;font-weight:600}.note{font-size:12px;color:var(--muted);line-height:1.5;margin:15px 0 0}
        @media(max-width:650px){.box{padding:22px;border-radius:16px}.grid{grid-template-columns:1fr}body{padding:18px 10px}h1{font-size:23px}}
    </style>
</head>
<body>
<div class="box">
    <div class="brand"><div class="mark">K</div><div class="brand-name">KPI Dashboard System</div></div>
    <h1>Create your account</h1>
    <p class="subtitle">Submit your employee information for Super Admin approval.</p>

    @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

    <form method="post" action="{{ route('register.store') }}" id="register-form">
        @csrf
        <div class="section">
            <h3>Personal &amp; Employee Information</h3>
            <div class="grid">
                <div class="field"><label class="label">Employee Code *</label><input class="input" name="employee_code" value="{{ old('employee_code') }}" required maxlength="50" placeholder="EMP-0001"></div>
                <div class="field"><label class="label">Full Name *</label><input class="input" name="name" value="{{ old('name') }}" required maxlength="255"></div>
                <div class="field"><label class="label">Email / Login *</label><input class="input" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></div>
                <div class="field"><label class="label">Phone</label><input class="input" name="phone" value="{{ old('phone') }}" maxlength="30"></div>
                <div class="field"><label class="label">Date of Birth</label><input class="input" type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"></div>
                <div class="field"><label class="label">Gender</label><select class="input" name="gender"><option value="">— Select —</option>@foreach(['Male','Female','Other'] as $gender)<option value="{{ $gender }}" @selected(old('gender')===$gender)>{{ $gender }}</option>@endforeach</select></div>
                <div class="field"><label class="label">Join Date</label><input class="input" type="date" name="join_date" value="{{ old('join_date') }}"></div>
                <div class="field"><label class="label">Department</label><select class="input" name="department_id"><option value="">— Select Department —</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id')==$d->id)>{{ $d->name }}</option>@endforeach</select></div>
                <div class="field"><label class="label">Unit</label><select class="input" name="unit_id"><option value="">— Select Unit —</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected(old('unit_id')==$u->id)>{{ $u->name }}</option>@endforeach</select></div>
                <div class="field"><label class="label">Job Title</label><select class="input" name="job_title_id"><option value="">— Select Job Title —</option>@foreach($jobTitles as $jt)<option value="{{ $jt->id }}" @selected(old('job_title_id')==$jt->id)>{{ $jt->name }} · {{ $jt->level ?? '—' }}</option>@endforeach</select></div>
            </div>
            <div class="field" style="margin-top:15px"><label class="label">Notes</label><textarea class="input" name="notes" rows="3" maxlength="500">{{ old('notes') }}</textarea></div>
        </div>

        <div class="section">
            <h3>Account Security</h3>
            <div class="grid">
                <div class="field"><label class="label">Password *</label><input class="input" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
                <div class="field"><label class="label">Confirm Password *</label><input class="input" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></div>
            </div>
            <p class="note">Your account will remain inactive until the Super Admin reviews and approves the registration. Access permissions are assigned by an administrator.</p>
        </div>

        <div class="captcha-box" id="captcha-box">
            <span class="captcha-label">Security check</span>
            <div class="slider-track" id="slider-track" role="slider" aria-label="Slide to complete security check" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" tabindex="0">
                <div class="slider-fill" id="slider-fill"></div><div class="slider-target" id="slider-target" style="left:{{ $captchaTarget }}%"></div><div class="slider-thumb" id="slider-thumb">›</div>
            </div>
            <div class="captcha-success">✓ Verification complete</div>
            <p class="hint">Drag the blue button to the circle. Works with mouse or touch.</p>
            <input type="hidden" name="captcha_position" id="captcha_position" value="">
        </div>

        <button class="button" type="submit">Submit Registration</button>
    </form>
    <a class="back" href="{{ route('login') }}">← Back to Sign in</a>
</div>
<script>
(function(){const track=document.getElementById('slider-track'),thumb=document.getElementById('slider-thumb'),fill=document.getElementById('slider-fill'),target=document.getElementById('slider-target'),hidden=document.getElementById('captcha_position'),box=document.getElementById('captcha-box');let dragging=false,verified=false,position=0;function targetPos(){return parseFloat(getComputedStyle(target).left)/track.getBoundingClientRect().width*100}function setPosition(x){const r=track.getBoundingClientRect(),max=r.width-thumb.offsetWidth-8;position=Math.max(0,Math.min(100,((x-r.left-thumb.offsetWidth/2-3)/max)*100));thumb.style.transform='translateX('+(max*position/100)+'px)';fill.style.width=Math.min(100,position+4)+'%';track.setAttribute('aria-valuenow',Math.round(position))}function reset(){position=0;thumb.style.transform='translateX(0)';fill.style.width='0';hidden.value='';track.setAttribute('aria-valuenow','0')}function finish(){if(Math.abs(position-targetPos())<=8){verified=true;hidden.value=position.toFixed(2);box.classList.add('verified');thumb.textContent='✓'}else reset()}thumb.addEventListener('pointerdown',e=>{if(verified)return;dragging=true;thumb.classList.add('dragging');thumb.setPointerCapture(e.pointerId);setPosition(e.clientX)});thumb.addEventListener('pointermove',e=>{if(dragging&&!verified)setPosition(e.clientX)});thumb.addEventListener('pointerup',()=>{if(!verified){dragging=false;thumb.classList.remove('dragging');finish()}});track.addEventListener('keydown',e=>{if(verified)return;if(['ArrowRight','ArrowUp'].includes(e.key)){e.preventDefault();setPosition(track.getBoundingClientRect().left+track.clientWidth*(Math.min(100,position+5)/100));finish()}if(['ArrowLeft','ArrowDown'].includes(e.key)){e.preventDefault();setPosition(track.getBoundingClientRect().left+track.clientWidth*(Math.max(0,position-5)/100))}});document.getElementById('register-form').addEventListener('submit',e=>{if(!verified){e.preventDefault();alert('Please complete the slider security check.')}})})();
</script>
</body>
</html>
