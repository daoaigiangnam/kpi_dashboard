@extends('layouts.admin')
@section('title','IT Outsourcing Tools')
@section('content')
<style>
.it-bulk-card{overflow:hidden}
.it-bulk-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.it-bulk-progress{height:9px;background:#e9eef3;border-radius:99px;overflow:hidden;margin:10px 0}
.it-bulk-progress>div{height:100%;width:0;transition:width .25s ease}
.it-bulk-table-wrap{overflow:auto;border:1px solid #dfe5ea;border-radius:8px;margin-top:14px}
.it-bulk-table{width:max-content;min-width:100%;border-collapse:separate;border-spacing:0;font-size:13px;background:#fff}
.it-bulk-table th{position:sticky;top:0;background:#f3f6f8;font-weight:700;white-space:nowrap;z-index:2}
.it-bulk-table th,.it-bulk-table td{padding:8px 10px;border-right:1px solid #e5e9ed;border-bottom:1px solid #e5e9ed;vertical-align:top;white-space:nowrap}
.it-bulk-table td.wrap{white-space:normal;min-width:180px;max-width:360px;word-break:break-word}
.it-bulk-table th:first-child,.it-bulk-table td:first-child{position:sticky;left:0;background:#fff;z-index:3}
.it-bulk-table th:nth-child(2),.it-bulk-table td:nth-child(2){position:sticky;left:150px;background:#fff;z-index:3}
.it-bulk-table th:first-child,.it-bulk-table th:nth-child(2){background:#f3f6f8;z-index:4}
.it-bulk-table tr:hover td{background:#f8fbfd}
.it-bulk-status-ok{font-weight:700}
.it-bulk-status-error{font-weight:700}
.it-bulk-section-title{margin:0 0 4px}
</style>

<div class="card">
    <h2>IT Outsourcing Tools</h2>
    <p class="muted">Domain, DNS, SSL/TLS, Website, Email, IP/Hosting and bulk audit.</p>
    <form id="audit-form" style="margin-top:16px">
        @csrf
        <div style="display:grid;grid-template-columns:2fr 1fr 2fr auto;gap:12px;align-items:end">
            <div><label>Domain</label><input id="domain" required placeholder="example.com"></div>
            <div><label>WAN IP (optional)</label><input id="wan_ip" placeholder="1.2.3.4"></div>
            <div><label>DKIM selectors (optional)</label><input id="dkim_selectors" placeholder="selector1, selector2"></div>
            <button type="submit">Run Audit</button>
        </div>
    </form>
    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
        <a class="button" href="{{ route('admin.it_tools.history') }}">Audit History</a>
        <a class="button" href="{{ route('admin.it_tools.history.export') }}">Export Excel</a>
        <a class="button" href="{{ route('admin.it_tools.bulk_template') }}">Download Bulk Template</a>
    </div>
</div>

<div class="card it-bulk-card" style="margin-top:20px">
    <h3>Bulk Audit</h3>
    <p class="muted">Paste one domain per line, or upload the Excel/CSV template.</p>
    <textarea id="bulk-items" rows="8" style="width:100%;font-family:monospace" placeholder="example.com\nexample.vn,1.2.3.4"></textarea>
    <div class="it-bulk-toolbar" style="margin-top:10px">
        <button id="bulk-run" type="button">Run Bulk Audit</button>
        <input id="bulk-file" type="file" accept=".xlsx,.xls,.csv,.txt">
        <button id="bulk-import" type="button">Import File</button>
        <span id="bulk-import-info" class="muted"></span>
    </div>
</div>

<div id="result" style="margin-top:20px"></div>
<div id="bulk-result" style="margin-top:20px"></div>

<script>
const csrf=document.querySelector('[name=_token]').value;
const esc=v=>String(v??'').replace(/[&<>\"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[c]));
const formatDays=v=>{if(v===null||v===undefined||v==='')return'N/A';const n=Number(v);return Number.isFinite(n)?Math.floor(n).toLocaleString('en-US')+' days':String(v);};

async function requestAudit(item){
 const r=await fetch('{{ route('admin.it_tools.audit') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({domain:item.domain,wan_ip:item.wan_ip||null,dkim_selectors:item.dkim_selectors||null})});
 const text=await r.text();let data={};
 try{data=text?JSON.parse(text):{};}catch(_){throw new Error('Server returned a non-JSON response (HTTP '+r.status+').');}
 if(!r.ok)throw new Error(data.message||('Audit failed (HTTP '+r.status+')'));
 return data;
}

function bulkValue(item,key){const a=item.audit||{},d=a.domain_audit||{},s=a.ssl_audit||{},w=(a.website_audit||{}).https||{},i=a.ip_audit||{},e=a.email_audit||{},dns=a.dns_audit||{},p=a.provider_detection||{};return {domain:item.domain,status:item.status==='ok'?'OK':'ERROR',domainExpiry:d.expires_at||'N/A',domainDays:formatDays(d.days_remaining),domainSource:d.source||'—',website:w.online?'ONLINE':(w.status?'UNREACHABLE':'OFFLINE'),https:w.status??'—',response:w.response_time_ms??'—',sslVendor:s.vendor||'N/A',sslExpiry:s.valid_to||'N/A',sslDays:formatDays(s.days_remaining),hostname:s.verify?'YES':'NO',tls:s.tls_version||'—',cipher:s.cipher||'—',ip:i.ip||'N/A',asn:i.asn||'—',network:i.network||i.organization||i.provider||'N/A',organization:i.organization||'—',dnsProvider:dns.dns_provider||p.dns_provider||'N/A',nameservers:(dns.dns_nameservers||[]).join(', ')||'N/A',dnssec:dns.dnssec?'DETECTED':'NOT DETECTED',dnsCounts:`${(dns.records?.A||[]).length} IPv4 · ${(dns.records?.AAAA||[]).length} IPv6 · ${(dns.records?.NS||[]).length} NS · ${(dns.records?.MX||[]).length} MX`,dnsTypes:(dns.record_types_found||Object.keys(dns.records||{}).filter(k=>(dns.records[k]||[]).length)).join(', '),mail:e.provider||'N/A',spf:e.spf_present?'PASS':'MISSING',dmarc:e.dmarc_present?'PASS':'MISSING',dkim:Object.entries(e.dkim||{}).map(([k,v])=>k+': '+(v.present?'PASS':'MISSING')).join('; ')||'Not checked',mta:e.mta_sts_present?'PASS':'MISSING',tlsRpt:e.tls_rpt_present?'PASS':'MISSING',cdn:p.cdn||'—',waf:p.waf||'—',hosting:p.hosting_provider||'—',error:item.error||''};}

function bulkResultRow(item){
 const v=bulkValue(item);
 return `<tr><td>${esc(v.domain)}</td><td class="${v.status==='OK'?'it-bulk-status-ok':'it-bulk-status-error'}">${esc(v.status)}</td><td>${esc(v.domainExpiry)}</td><td>${esc(v.domainDays)}</td><td>${esc(v.domainSource)}</td><td>${esc(v.website)}</td><td>${esc(v.https)}</td><td>${esc(v.response)}</td><td>${esc(v.sslVendor)}</td><td>${esc(v.sslExpiry)}</td><td>${esc(v.sslDays)}</td><td>${esc(v.hostname)}</td><td>${esc(v.tls)}</td><td>${esc(v.cipher)}</td><td>${esc(v.ip)}</td><td>${esc(v.asn)}</td><td>${esc(v.network)}</td><td class="wrap">${esc(v.organization)}</td><td>${esc(v.dnsProvider)}</td><td class="wrap">${esc(v.nameservers)}</td><td>${esc(v.dnssec)}</td><td>${esc(v.dnsCounts)}</td><td class="wrap">${esc(v.dnsTypes)}</td><td>${esc(v.mail)}</td><td class="wrap">${esc(v.spf)}</td><td class="wrap">${esc(v.dmarc)}</td><td class="wrap">${esc(v.dkim)}</td><td>${esc(v.mta)}</td><td>${esc(v.tlsRpt)}</td><td>${esc(v.cdn)}</td><td>${esc(v.waf)}</td><td>${esc(v.hosting)}</td><td class="wrap">${esc(v.error)}</td></tr>`;
}

function renderBulkResults(target,results,total,progress){
 const headers=['Domain','Status','Domain Expiry','Domain Days','Domain Source','Website','HTTPS','Response ms','SSL Vendor','SSL Expiry','SSL Days','Hostname Match','TLS Version','Cipher','IP','ASN','Network','Organization','DNS Provider','Nameservers','DNSSEC','DNS Counts','DNS Record Types','Mail Provider','SPF','DMARC','DKIM','MTA-STS','TLS-RPT','CDN','WAF','Hosting Provider','Error'];
 const rows=results.map(bulkResultRow).join('');
 target.innerHTML=`<div class="card"><h3 class="it-bulk-section-title">Bulk Result</h3><p><strong>${results.length} / ${total}</strong> completed</p><div class="it-bulk-progress"><div style="width:${progress}%"></div></div><div class="it-bulk-table-wrap"><table class="it-bulk-table"><thead><tr>${headers.map(h=>`<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows}</tbody></table></div><p class="muted" style="margin-top:10px">DNS summary is included; individual DNS records are intentionally omitted from Bulk Summary.</p></div>`;
}

async function runBulk(items){
 const target=document.getElementById('bulk-result');const button=document.getElementById('bulk-run');
 if(!items.length){target.innerHTML='<div class="card">Please enter at least one domain.</div>';return;}
 button.disabled=true;button.textContent='Running…';const results=[];
 target.innerHTML=`<div class="card"><h3>Bulk Audit</h3><p>0 / ${items.length} completed</p><div class="it-bulk-progress"><div></div></div><p id="bulk-current" class="muted">Preparing…</p></div>`;
 try{
  for(let index=0;index<items.length;index++){
   const item=items[index],current=document.getElementById('bulk-current');
   if(current)current.textContent=`[${index+1}/${items.length}] Checking ${item.domain}…`;
   try{const audit=await requestAudit(item);results.push({status:'ok',domain:item.domain,wan_ip:item.wan_ip||null,audit});}
   catch(error){results.push({status:'error',domain:item.domain,wan_ip:item.wan_ip||null,error:error.message});}
   renderBulkResults(target,results,items.length,Math.round(((index+1)/items.length)*100));
  }
  const title=target.querySelector('.it-bulk-section-title');if(title)title.insertAdjacentHTML('beforebegin','<p><strong>Bulk audit completed.</strong></p>');
 }finally{button.disabled=false;button.textContent='Run Bulk Audit';}
}

async function runBulkFromTextarea(){
 const lines=document.getElementById('bulk-items').value.split(/\r?\n/).map(v=>v.trim()).filter(Boolean).slice(0,100);
 const items=lines.map(line=>{try{if(line.startsWith('{'))return JSON.parse(line);}catch(_){}const p=line.split(',').map(v=>v.trim());return{domain:p[0],wan_ip:p[1]||null};});
 await runBulk(items);
}

document.getElementById('audit-form').addEventListener('submit',async e=>{e.preventDefault();const result=document.getElementById('result');result.innerHTML='<div class="card">Checking…</div>';try{const data=await requestAudit({domain:document.getElementById('domain').value,wan_ip:document.getElementById('wan_ip').value||null,dkim_selectors:document.getElementById('dkim_selectors').value||null});renderAudit(result,data);}catch(error){result.innerHTML='<div class="card">Audit failed: '+esc(error.message)+'</div>';}});
document.getElementById('bulk-run').addEventListener('click',runBulkFromTextarea);
document.getElementById('bulk-items').addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key==='Enter'){e.preventDefault();runBulkFromTextarea();}});
document.getElementById('bulk-import').addEventListener('click',async()=>{const file=document.getElementById('bulk-file').files[0],info=document.getElementById('bulk-import-info');if(!file){info.textContent='Choose an Excel/CSV file first.';return;}info.textContent='Importing…';try{const form=new FormData();form.append('file',file);const r=await fetch('{{ route('admin.it_tools.bulk_import') }}',{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:form});const text=await r.text();let data={};try{data=text?JSON.parse(text):{};}catch(_){throw new Error('Server returned a non-JSON response (HTTP '+r.status+').');}if(!r.ok)throw new Error(data.message||'Import failed');document.getElementById('bulk-items').value=(data.items||[]).map(item=>`${item.domain}${item.wan_ip?','+item.wan_ip:''}`).join('\n');info.textContent=`${data.count} rows imported. Review the list, then click Run Bulk Audit.`;}catch(error){info.textContent='Import failed: '+error.message;}});

function statusLabel(online,status){if(online)return'ONLINE';if(status)return'UNREACHABLE';return'OFFLINE';}
function formatRecordValue(row,type){if(!row||typeof row!=='object')return'';if(type==='MX')return`${row.target||row.value||''}${row.pri!==undefined?' (priority '+row.pri+')':''}`;if(type==='SOA')return`MNAME: ${row.mname||''} · RNAME: ${row.rname||''} · Serial: ${row.serial??''} · Refresh: ${row.refresh??''} · Retry: ${row.retry??''} · Expire: ${row.expire??''} · Minimum TTL: ${row['minimum-ttl']??row.minimum??''}`;if(row.txt||row.value||row.target||row.ip||row.ipv6||row.cname)return row.txt||row.value||row.target||row.ip||row.ipv6||row.cname;return Object.entries(row).filter(([k])=>!['host','class','ttl','type'].includes(k)).map(([k,v])=>`${k}: ${typeof v==='object'?JSON.stringify(v):v}`).join(' · ');}
function dnsRecordsHtml(dns){const records=dns.records||{};const types=Object.keys(records).filter(type=>(records[type]||[]).length);let html='<div class="card" style="margin-top:20px"><h3>DNS Records</h3>';html+=`<p><strong>Provider:</strong> ${esc(dns.dns_provider||'Unknown provider')}<br><strong>Nameservers:</strong> ${esc((dns.dns_nameservers||[]).join(', ')||'N/A')}<br><strong>Record types found:</strong> ${esc((dns.record_types_found||types).join(', ')||'None')}<br><strong>Query:</strong> ${esc(dns.dns_query_mode||'DNS queries')}<br><strong>DNSSEC:</strong> ${dns.dnssec?'Detected':'Not detected'}<br><strong>SPF:</strong> ${esc(dns.spf||'MISSING')}<br><strong>DMARC:</strong> ${esc(dns.dmarc||'MISSING')}</p>`;html+='<div style="overflow:auto"><table><thead><tr><th>Type</th><th>TTL</th><th>Record</th></tr></thead><tbody>';types.forEach(type=>{const rows=records[type]||[];rows.forEach(row=>{html+=`<tr><td><strong>${esc(type)}</strong></td><td>${esc(row.ttl??'—')}</td><td style="white-space:pre-wrap;word-break:break-word">${esc(formatRecordValue(row,type))}</td></tr>`})});html+='</tbody></table></div></div>';return html;}
function renderAudit(target,data){const d=data.domain_audit||{},s=data.ssl_audit||{},w=data.website_audit||{},i=data.ip_audit||{},e=data.email_audit||{},dns=data.dns_audit||{},https=(w.https||{});const nsCount=(dns.records?.NS||[]).length,aCount=(dns.records?.A||[]).length,aaaaCount=(dns.records?.AAAA||[]).length,mxCount=(dns.records?.MX||[]).length;const dnsLabel=dns.dns_provider||(nsCount?'Unknown provider':'No NS records'),ipNetwork=i.network||i.organization||i.provider||'Provider unavailable',domainExpiry=d.expires_at||(d.status==='unavailable'?'Registry data unavailable':'N/A'),domainDays=d.days_remaining??'N/A',domainSource=d.source||'—',ipMeta=[i.asn,i.organization].filter(Boolean).join(' · ');target.innerHTML=`<div class="grid"><div class="card"><div class="muted">Domain Expiry</div><h3>${esc(domainExpiry)}</h3><div>${esc(formatDays(domainDays))} · ${esc(domainSource)}</div></div><div class="card"><div class="muted">SSL</div><h3>${esc(s.vendor||'N/A')}</h3><div>${esc(s.valid_to||'N/A')} · ${esc(formatDays(s.days_remaining))}</div></div><div class="card"><div class="muted">Website</div><h3>${statusLabel(https.online,https.status)}</h3><div>HTTPS ${esc(https.status??'—')} · ${esc(https.response_time_ms??'—')}${https.transport_verified===false?' · TLS transport unverified':''}</div></div><div class="card"><div class="muted">IP / Network</div><h3>${esc(i.ip||'N/A')}</h3><div>${esc(ipNetwork)}${ipMeta?'<br>'+esc(ipMeta):''}</div></div><div class="card"><div class="muted">Email</div><h3>${esc(e.provider||'Unknown provider')}</h3><div>SPF ${e.spf_present?'PASS':'MISSING'} · DMARC ${e.dmarc_present?'PASS':'MISSING'}</div></div><div class="card"><div class="muted">DNS</div><h3>${esc(dnsLabel)}</h3><div>${aCount} IPv4 · ${aaaaCount} IPv6 · ${nsCount} NS · ${mxCount} MX</div></div></div>${dnsRecordsHtml(dns)}<div class="card" style="margin-top:20px"><h3>Audit JSON</h3><pre style="white-space:pre-wrap">${esc(JSON.stringify(data,null,2))}</pre></div>`;}
</script>
@endsection