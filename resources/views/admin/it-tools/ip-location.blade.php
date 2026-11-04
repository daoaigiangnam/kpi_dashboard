@extends('layouts.admin')
@section('title','IP Location')
@section('content')
<div class="card" style="max-width:1100px">
    <h2 style="margin-top:0">🌐 IP Location</h2>
    <p class="muted">Tra cứu vị trí địa lý, ISP, ASN, Organization, Reverse DNS và timezone của một IP Public.</p>
    <form id="ip-location-form" style="margin-top:18px">
        @csrf
        <div style="display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:end">
            <div>
                <label>IP Address</label>
                <input class="input" id="ip" name="ip" placeholder="8.8.8.8" autocomplete="off">
                <small class="muted">Để trống để kiểm tra Public IP của server.</small>
            </div>
            <button class="btn" type="submit" id="lookup-btn">🔎 Lookup</button>
            <button class="btn gray" type="button" id="clear-btn">Clear</button>
        </div>
    </form>
</div>

<div id="result" style="margin-top:18px"></div>

<script>
const form=document.getElementById('ip-location-form');
const result=document.getElementById('result');
const button=document.getElementById('lookup-btn');
const csrf=document.querySelector('[name=_token]').value;
const esc=v=>String(v??'—').replace(/[&<>\"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[c]));
const row=(label,value)=>`<div style="padding:11px 12px;border-bottom:1px solid #e8eee9"><div class="muted">${esc(label)}</div><div style="font-weight:600;margin-top:3px;word-break:break-word">${esc(value)}</div></div>`;

form.addEventListener('submit',async e=>{
 e.preventDefault();
 button.disabled=true; button.textContent='⏳ Looking up...';
 result.innerHTML='<div class="card">Đang truy vấn IP location...</div>';
 try{
   const r=await fetch('{{ route('admin.it_tools.ip_location') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({ip:document.getElementById('ip').value.trim()||null})});
   const data=await r.json();
   if(!r.ok) throw new Error(data.message||'IP lookup failed.');
   const location=[data.city,data.region,data.country].filter(Boolean).join(', ');
   result.innerHTML=`<div class="card">
     <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap"><div><h3 style="margin:0">${esc(data.flag||'🌐')} ${esc(data.ip)}</h3><div class="muted">${esc(data.type||'IP')} · ${esc(location||'Unknown location')}</div></div><div class="btn gray" style="cursor:default">ASN ${esc(data.asn||'—')}</div></div>
     <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1px;background:#e8eee9;margin-top:16px;border:1px solid #e8eee9">
       ${row('Country',data.country_code?data.country+' ('+data.country_code+')':data.country)}
       ${row('Region',data.region)}
       ${row('City',data.city)}
       ${row('Postal Code',data.postal)}
       ${row('Latitude',data.latitude)}
       ${row('Longitude',data.longitude)}
       ${row('ISP',data.isp)}
       ${row('Organization',data.org)}
       ${row('ASN',data.asn)}
       ${row('Network Domain',data.domain)}
       ${row('Reverse DNS',data.reverse_dns)}
       ${row('Timezone',data.timezone)}
       ${row('UTC Offset',data.timezone_utc)}
       ${row('Currency',data.currency_code?data.currency+' ('+data.currency_code+')':data.currency)}
       ${row('Calling Code',data.calling_code)}
     </div>
     <p class="muted" style="margin-bottom:0;margin-top:12px">IP geolocation is approximate and should not be treated as an exact physical address.</p>
   </div>`;
 }catch(err){ result.innerHTML='<div class="error">'+esc(err.message)+'</div>'; }
 finally{button.disabled=false;button.textContent='🔎 Lookup';}
});

document.getElementById('clear-btn').addEventListener('click',()=>{document.getElementById('ip').value='';result.innerHTML='';});
</script>

<style>
@media(max-width:700px){#ip-location-form>div{grid-template-columns:1fr!important}.card [style*="repeat(3"]{grid-template-columns:1fr!important}}
</style>
@endsection
