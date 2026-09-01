<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>KPI Dashboard System — Sign in</title>
    <style>
        *{box-sizing:border-box}
        :root{--navy:#0f172a;--slate:#475569;--muted:#64748b;--line:#dbe4ee;--blue:#2563eb;--blue-dark:#1d4ed8;--blue-soft:#eff6ff}
        body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:linear-gradient(135deg,#dce8f7 0%,#edf3fa 42%,#dfeef8 100%);display:grid;place-items:center;min-height:100vh;margin:0;color:var(--navy);position:relative;overflow:hidden}
        body:before{content:"";position:absolute;width:900px;height:900px;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.25) 0%,rgba(59,130,246,.14) 30%,rgba(59,130,246,.04) 55%,rgba(59,130,246,0) 72%);top:-520px;left:-280px;pointer-events:none}
        body:after{content:"";position:absolute;width:850px;height:850px;border-radius:50%;background:radial-gradient(circle,rgba(14,165,233,.22) 0%,rgba(56,189,248,.12) 32%,rgba(56,189,248,.04) 55%,rgba(56,189,248,0) 73%);bottom:-500px;right:-240px;pointer-events:none}
        .box{position:relative;z-index:1;background:rgba(255,255,255,.98);backdrop-filter:blur(18px);width:min(410px,calc(100% - 32px));padding:32px;border:1px solid rgba(203,213,225,.95);border-radius:18px;box-shadow:0 30px 80px rgba(15,23,42,.18),0 6px 18px rgba(15,23,42,.08)}
        .brand{display:flex;align-items:center;gap:10px;margin-bottom:28px}
        .brand-mark{width:36px;height:36px;border-radius:10px;background:linear-gradient(145deg,#3b82f6,#1d4ed8);color:#fff;display:grid;place-items:center;font-size:17px;font-weight:800;box-shadow:0 7px 16px rgba(37,99,235,.30)}
        .brand-name{font-size:17px;font-weight:750;letter-spacing:-.25px;color:var(--navy)}
        h2{font-size:25px;line-height:1.2;margin:0 0 7px;letter-spacing:-.5px}
        .subtitle{color:var(--muted);margin:0 0 24px;font-size:14px}
        .field{margin-bottom:17px}
        .label{display:block;margin-bottom:7px;font-size:13px;font-weight:650;color:#334155}
        .password-wrap{position:relative}
        .input{width:100%;height:44px;padding:11px 12px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:var(--navy);font-size:14px;outline:none;transition:border-color .15s,box-shadow .15s,background .15s}
        .input:hover{border-color:#b6c4d4}
        .input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.12);background:#fff}
        .password-wrap .input{padding-right:72px}
        .show{position:absolute;right:7px;top:5px;height:34px;border:0;background:transparent;color:var(--blue);font-size:12px;font-weight:700;cursor:pointer;padding:6px 8px}
        .show:hover{color:var(--blue-dark)}
        .captcha-box{padding:13px;background:linear-gradient(145deg,#f8fbff,#f3f7fb);border:1px solid #dce6f0;border-radius:11px;margin-bottom:13px}
        .captcha-label{display:block;font-size:13px;font-weight:650;color:#334155;margin-bottom:9px}
        .slider-track{position:relative;height:42px;border-radius:21px;background:#e6edf5;border:1px solid #cbd7e4;touch-action:none;user-select:none;overflow:hidden}
        .slider-target{position:absolute;top:3px;height:34px;width:34px;border-radius:50%;border:2px dashed #94a3b8;background:#fff;transform:translateX(-50%);pointer-events:none}
        .slider-fill{position:absolute;left:0;top:0;height:100%;width:0;background:linear-gradient(90deg,#dbeafe,#bfdbfe);pointer-events:none}
        .slider-thumb{position:absolute;top:3px;left:3px;width:34px;height:34px;border-radius:50%;background:linear-gradient(145deg,#3b82f6,#2563eb);color:#fff;display:grid;place-items:center;font-weight:800;box-shadow:0 4px 10px rgba(37,99,235,.30);cursor:grab;z-index:2;transform:translateX(0)}
        .slider-thumb.dragging{cursor:grabbing}
        .slider-success{display:none;text-align:center;color:#166534;font-weight:700;padding-top:8px;font-size:12px}
        .captcha-box.verified .slider-success{display:block}
        .captcha-box.verified .slider-track{opacity:.75}
        .captcha-hint{font-size:11px;color:var(--muted);margin:8px 0 0;line-height:1.35}
        .button{width:100%;height:44px;padding:11px;border:0;border-radius:9px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 7px 16px rgba(37,99,235,.18);transition:filter .15s,transform .05s,box-shadow .15s}
        .button:hover{filter:brightness(1.04);box-shadow:0 9px 20px rgba(37,99,235,.23)}
        .button:active{transform:translateY(1px)}
        .forgot{display:block;text-align:center;margin-top:17px;color:#2563eb;text-decoration:none;font-size:13px;font-weight:600}
        .forgot:hover{text-decoration:underline}
        .error,.status{padding:11px 12px;border-radius:9px;margin-bottom:16px;font-size:13px}
        .error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
        .status{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
        .security-note{font-size:11px;color:var(--muted);margin:0 0 16px;line-height:1.4}
        @media(max-width:480px){body{background:linear-gradient(135deg,#e2ebf7 0%,#eef4fa 48%,#e3eff8 100%)}.box{padding:26px;width:min(410px,calc(100% - 24px));border-radius:15px}.brand{margin-bottom:24px}h2{font-size:23px}}
    </style>
</head>
<body>
<div class="box">
    <div class="brand">
        <div class="brand-mark">K</div>
        <div class="brand-name">KPI Dashboard System</div>
    </div>

    <h2>Welcome back</h2>
    <p class="subtitle">Sign in to continue to your dashboard.</p>

    @if(session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('login.attempt') }}" id="login-form">
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

        <div class="captcha-box" id="captcha-box">
            <span class="captcha-label">Security check</span>
            <div class="slider-track" id="slider-track" role="slider" aria-label="Slide to complete security check" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" tabindex="0">
                <div class="slider-fill" id="slider-fill"></div>
                <div class="slider-target" id="slider-target" style="left:{{ $captchaTarget }}%"></div>
                <div class="slider-thumb" id="slider-thumb">›</div>
            </div>
            <div class="slider-success">✓ Verification complete</div>
            <p class="captcha-hint">Drag the blue button to the circle. Works with mouse or touch.</p>
            <input type="hidden" name="captcha_position" id="captcha_position" value="">
        </div>
        <p class="security-note">Complete the security check before signing in.</p>

        <button class="button" type="submit">Sign in</button>
    </form>

    <a class="forgot" href="{{ route('password.request') }}">Forgot password?</a>
</div>
<script>
function togglePassword(){const input=document.getElementById('password');const button=document.querySelector('.show');const show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';}
(function(){
 const track=document.getElementById('slider-track'), thumb=document.getElementById('slider-thumb'), fill=document.getElementById('slider-fill'), hidden=document.getElementById('captcha_position'), target=document.getElementById('slider-target'), box=document.getElementById('captcha-box');
 const targetPosition=parseFloat(getComputedStyle(target).left)/track.getBoundingClientRect().width*100;
 let dragging=false, verified=false, position=0;
 function setPosition(clientX){
   const rect=track.getBoundingClientRect(), max=rect.width-thumb.offsetWidth-6;
   position=Math.max(0,Math.min(100,((clientX-rect.left-thumb.offsetWidth/2-3)/max)*100));
   thumb.style.transform='translateX('+((max*position/100))+'px)';
   fill.style.width=Math.min(100,position+4)+'%';
   track.setAttribute('aria-valuenow',Math.round(position));
 }
 function reset(){position=0;thumb.style.transform='translateX(0)';fill.style.width='0';track.setAttribute('aria-valuenow','0');}
 function finish(){
   if(Math.abs(position-targetPosition)<=8){
     verified=true;hidden.value=position.toFixed(2);box.classList.add('verified');thumb.textContent='✓';thumb.style.cursor='default';
   }else{reset();hidden.value='';}
 }
 thumb.addEventListener('pointerdown',e=>{if(verified)return;dragging=true;thumb.classList.add('dragging');thumb.setPointerCapture(e.pointerId);setPosition(e.clientX);});
 thumb.addEventListener('pointermove',e=>{if(dragging&&!verified)setPosition(e.clientX);});
 thumb.addEventListener('pointerup',()=>{if(!verified){dragging=false;thumb.classList.remove('dragging');finish();}});
 track.addEventListener('keydown',e=>{if(verified)return;if(['ArrowRight','ArrowUp'].includes(e.key)){e.preventDefault();setPosition(track.getBoundingClientRect().left+track.clientWidth*(Math.min(100,position+5)/100));finish();}if(['ArrowLeft','ArrowDown'].includes(e.key)){e.preventDefault();setPosition(track.getBoundingClientRect().left+track.clientWidth*(Math.max(0,position-5)/100));}});
 document.getElementById('login-form').addEventListener('submit',e=>{if(!verified){e.preventDefault();alert('Please complete the slider security check.');}});
})();
</script>
</body>
</html>
