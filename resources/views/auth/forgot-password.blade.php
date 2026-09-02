<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Forgot Password - KPI Dashboard</title>
    <style>
        *{box-sizing:border-box}body{font-family:Inter,Arial,sans-serif;background:#f4f8f5;display:grid;place-items:center;min-height:100vh;margin:0;color:#172033}.box{background:#fff;width:min(420px,calc(100% - 32px));padding:30px;border-radius:14px;box-shadow:0 12px 35px #17321f14;border:1px solid #e1e9e3}h2{margin:0 0 8px}p{color:#64748b;line-height:1.5}.input{width:100%;padding:12px;border:1px solid #cbd8cf;border-radius:8px;margin:7px 0 16px}.btn{width:100%;padding:12px;border:0;border-radius:8px;background:#238b57;color:#fff;font-weight:700;cursor:pointer}.link{display:block;text-align:center;margin-top:16px;color:#23784e;text-decoration:none}.error,.status{padding:11px;border-radius:8px;margin-bottom:15px}.error{background:#fee2e2;color:#991b1b}.status{background:#dcfce7;color:#166534}.captcha-box{padding:12px;background:#f8faf9;border:1px solid #d7e3db;border-radius:8px;margin-bottom:16px}.captcha-label{display:block;font-weight:600;margin-bottom:8px}.slider-track{position:relative;height:42px;border-radius:21px;background:#e7eee9;border:1px solid #cbd8cf;touch-action:none;user-select:none;overflow:hidden}.slider-target{position:absolute;top:3px;height:34px;width:34px;border-radius:50%;border:2px dashed #8da397;background:#fff;transform:translateX(-50%);pointer-events:none}.slider-fill{position:absolute;left:0;top:0;height:100%;width:0;background:#d8eee0;pointer-events:none}.slider-thumb{position:absolute;top:3px;left:3px;width:34px;height:34px;border-radius:50%;background:#238b57;color:#fff;display:grid;place-items:center;font-weight:700;box-shadow:0 2px 7px #17321f26;cursor:grab;z-index:2;transform:translateX(0)}.slider-thumb.dragging{cursor:grabbing}.slider-success{display:none;text-align:center;color:#166534;font-weight:700;padding-top:8px;font-size:13px}.captcha-box.verified .slider-success{display:block}.captcha-box.verified .slider-track{opacity:.75}.captcha-hint{font-size:12px;color:#64748b;margin:8px 0 0}
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

    <form method="post" action="{{ route('password.email') }}" id="forgot-form">
        @csrf
        <label for="email">Email / Login</label>
        <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">

        <div class="captcha-box" id="captcha-box">
            <span class="captcha-label">Security Check</span>
            <div class="slider-track" id="slider-track" role="slider" aria-label="Slide to complete security check" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" tabindex="0">
                <div class="slider-fill" id="slider-fill"></div>
                <div class="slider-target" id="slider-target" style="left:{{ $captchaTarget }}%"></div>
                <div class="slider-thumb" id="slider-thumb">›</div>
            </div>
            <div class="slider-success">✓ Verification complete</div>
            <p class="captcha-hint">Drag the green button to the circle. Works with mouse or touch.</p>
            <input type="hidden" name="captcha_position" id="captcha_position" value="">
        </div>

        <button class="btn" type="submit">Send OTP & Reset Link</button>
    </form>

    <a class="link" href="{{ route('login') }}">← Back to Sign in</a>
</div>
<script>
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
 function finish(){if(Math.abs(position-targetPosition)<=8){verified=true;hidden.value=position.toFixed(2);box.classList.add('verified');thumb.textContent='✓';thumb.style.cursor='default';}else{reset();hidden.value='';}}
 thumb.addEventListener('pointerdown',e=>{if(verified)return;dragging=true;thumb.classList.add('dragging');thumb.setPointerCapture(e.pointerId);setPosition(e.clientX);});
 thumb.addEventListener('pointermove',e=>{if(dragging&&!verified)setPosition(e.clientX);});
 thumb.addEventListener('pointerup',()=>{if(!verified){dragging=false;thumb.classList.remove('dragging');finish();}});
 track.addEventListener('keydown',e=>{if(verified)return;if(['ArrowRight','ArrowUp'].includes(e.key)){e.preventDefault();setPosition(track.getBoundingClientRect().left+track.clientWidth*(Math.min(100,position+5)/100));finish();}if(['ArrowLeft','ArrowDown'].includes(e.key)){e.preventDefault();setPosition(track.getBoundingClientRect().left+track.clientWidth*(Math.max(0,position-5)/100));}});
 document.getElementById('forgot-form').addEventListener('submit',e=>{if(!verified){e.preventDefault();alert('Please complete the slider security check.');}});
})();
</script>
</body>
</html>
