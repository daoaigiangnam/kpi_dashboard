@extends('layouts.admin')
@section('title','IP Blacklist Check')
@section('content')
<div class="card" style="max-width:1100px">
    <h2 style="margin-top:0">🚫 IP Blacklist Check</h2>
    <p class="muted">Kiểm tra IP WAN/Public IPv4 có xuất hiện trên các DNS-based Blacklist (DNSBL) phổ biến hay không.</p>

    <form id="blacklist-form" style="margin-top:18px">
        @csrf
        <div style="display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:end">
            <div>
                <label>WAN / Public IPv4 *</label>
                <input class="input" id="ip" name="ip" placeholder="Ví dụ: 8.8.8.8" autocomplete="off" required>
                <small class="muted">Chỉ kiểm tra Public IPv4. Không hỗ trợ IP private/reserved.</small>
            </div>
            <button class="btn" type="submit" id="check-btn">🔎 Check Blacklist</button>
            <button class="btn gray" type="button" id="public-btn">🌐 Public IP</button>
        </div>
    </form>
</div>

<div id="result" style="margin-top:18px"></div>

<script>
const form=document.getElementById('blacklist-form');
const result=document.getElementById('result');
const button=document.getElementById('check-btn');
const publicButton=document.getElementById('public-btn');
const csrf=document.querySelector('[name=_token]').value;
const esc=v=>String(v??'—').replace(/[&<>\"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[c]));

form.addEventListener('submit',async e=>{
    e.preventDefault();
    const ip=document.getElementById('ip').value.trim();
    if(!ip) return;
    button.disabled=true;
    button.textContent='⏳ Checking...';
    result.innerHTML='<div class="card">Đang kiểm tra IP trên các DNSBL...</div>';
    try{
        const r=await fetch('{{ route('admin.it_tools.ip_blacklist') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({ip})});
        const data=await r.json();
        if(!r.ok) throw new Error(data.message||'Blacklist check failed.');
        const s=data.summary||{};
        const overall=s.overall==='listed'?'listed':(s.overall==='partial'?'partial':'clean');
        const badge=overall==='listed'?'🔴 LISTED':(overall==='partial'?'🟠 PARTIAL':'🟢 CLEAN');
        const badgeStyle=overall==='listed'?'background:#fcebea;color:#8f2f2c':(overall==='partial'?'background:#fff7cc;color:#735b00':'background:#e8f6ed;color:#24613f');
        const rows=(data.results||[]).map(x=>{
            const status=x.status==='listed'?'🔴 LISTED':(x.status==='error'?'⚠ ERROR':'🟢 CLEAN');
            const detail=x.listed && x.response ? 'Response: '+x.response.join(', ') : (x.error||'No DNSBL listing detected');
            return `<tr><td><strong>${esc(x.name)}</strong><div class="muted">${esc(x.zone)}</div></td><td>${status}</td><td>${esc(detail)}</td></tr>`;
        }).join('');
        result.innerHTML=`<div class="card">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
                <div><h3 style="margin:0">${esc(data.ip)}</h3><div class="muted">Checked: ${esc(data.checked_at)}</div></div>
                <div style="padding:9px 14px;border-radius:8px;font-weight:700;${badgeStyle}">${badge}</div>
            </div>
            <div class="grid" style="margin-top:16px">
                <div class="card"><div class="muted">Blacklist sources</div><strong>${esc(s.checked)}</strong></div>
                <div class="card"><div class="muted">Listed</div><strong>${esc(s.listed)}</strong></div>
                <div class="card"><div class="muted">Clean</div><strong>${esc(s.clean)}</strong></div>
                <div class="card"><div class="muted">Errors</div><strong>${esc(s.errors)}</strong></div>
            </div>
            <div class="table-wrap" style="margin-top:18px">
                <table class="table"><thead><tr><th>Blacklist</th><th>Status</th><th>Details</th></tr></thead><tbody>${rows}</tbody></table>
            </div>
            <div class="info" style="margin-top:16px;margin-bottom:0">${esc(data.note)}</div>
        </div>`;
    }catch(err){ result.innerHTML='<div class="error">'+esc(err.message)+'</div>'; }
    finally{button.disabled=false;button.textContent='🔎 Check Blacklist';}
});

publicButton.addEventListener('click',async()=>{
    publicButton.disabled=true;
    publicButton.textContent='⏳ Detecting...';
    try{
        const r=await fetch('{{ route('admin.it_tools.ip_location') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({ip:null})});
        const data=await r.json();
        if(!r.ok) throw new Error(data.message||'Unable to detect public IP.');
        document.getElementById('ip').value=data.ip||'';
    }catch(err){ result.innerHTML='<div class="error">'+esc(err.message)+'</div>'; }
    finally{publicButton.disabled=false;publicButton.textContent='🌐 Public IP';}
});
</script>
@endsection
