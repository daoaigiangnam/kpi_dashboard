@extends('layouts.admin')
@section('title','IT Outsourcing Tools')
@section('content')
<style>
.it-bulk-card{overflow:hidden}
.it-bulk-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.it-bulk-progress{height:9px;background:#e9eef3;border-radius:99px;overflow:hidden;margin:10px 0}
.it-bulk-progress>div{height:100%;width:0;transition:width .25s ease}
.it-bulk-table-wrap{overflow:auto;border:1px solid #d8e0e7;border-radius:10px;margin-top:14px;box-shadow:0 2px 8px rgba(20,40,60,.05);background:#fff}
.it-bulk-table{width:max-content;min-width:100%;border-collapse:separate;border-spacing:0;font-size:12.5px;background:#fff}
.it-bulk-table th,.it-bulk-table td{padding:8px 10px;border-right:1px solid #e3e8ed;border-bottom:1px solid #e3e8ed;vertical-align:top}
.it-bulk-table thead tr.group-row th{position:sticky;top:0;color:#fff;font-size:12px;letter-spacing:.2px;text-align:center;white-space:nowrap;padding:7px 10px;border-right:1px solid rgba(255,255,255,.22);z-index:5}
.it-bulk-table thead tr.group-row th.g-audit{background:#334e68}.it-bulk-table thead tr.group-row th.g-domain{background:#486581}.it-bulk-table thead tr.group-row th.g-web{background:#3c6e71}.it-bulk-table thead tr.group-row th.g-ssl{background:#6c5b7b}.it-bulk-table thead tr.group-row th.g-ip{background:#7b5e57}.it-bulk-table thead tr.group-row th.g-dns{background:#4f6d4f}.it-bulk-table thead tr.group-row th.g-mail{background:#8a6d3b}.it-bulk-table thead tr.group-row th.g-provider{background:#52616b}.it-bulk-table thead tr.group-row th.g-error{background:#7f1d1d}
.it-bulk-table thead tr.header-row th{position:sticky;top:32px;background:#f3f6f8;color:#172b4d;font-weight:700;white-space:nowrap;text-align:left;z-index:4}
.it-bulk-table tbody tr:nth-child(even) td{background:#fbfcfd}
.it-bulk-table tbody tr:hover td{background:#eef6fb}
.it-bulk-table td.wrap{white-space:normal;min-width:180px;max-width:360px;word-break:break-word;line-height:1.35}
.it-bulk-table th:first-child,.it-bulk-table td:first-child{position:sticky;left:0;z-index:6;min-width:160px}
.it-bulk-table th:nth-child(2),.it-bulk-table td:nth-child(2){position:sticky;left:160px;z-index:6;min-width:90px}
.it-bulk-table thead tr.header-row th:first-child,.it-bulk-table thead tr.header-row th:nth-child(2){background:#e7eef4}
.it-bulk-table tbody td:first-child,.it-bulk-table tbody td:nth-child(2){background:#fff}
.it-bulk-table tbody tr:nth-child(even) td:first-child,.it-bulk-table tbody tr:nth-child(even) td:nth-child(2){background:#fbfcfd}
.it-bulk-table td.status-ok{font-weight:700;text-align:center}
.it-bulk-table td.status-error{font-weight:700;text-align:center}
.it-bulk-table .status-online{font-weight:700}
.it-bulk-table .status-pass{font-weight:700}
.it-bulk-section-title{margin:0 0 4px}
.it-bulk-meta{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 2px}
.it-bulk-pill{display:inline-flex;align-items:center;padding:4px 9px;border:1px solid #d7e0e8;border-radius:999px;background:#f8fafc;font-size:12px}
.it-bulk-table .muted-cell{color:#718096}
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
const boolLabel=v=>v===true?'YES':v===false?'NO':'—';

async function requestAudit(item){
 const r=await fetch('{{ route('admin.it_tools.audit') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({domain:item.domain,wan_ip:item.wan_ip||null,dkim_selectors:item.dkim_selectors||null})});
 const text=await r.text();let data={};
 try{data=text?JSON.parse(text):{};}catch(_){throw new Error('Server returned a non-JSON response (HTTP '+r.status+').');}
 if(!r.ok)throw new Error(data.message||('Audit failed (HTTP '+r.status+')'));
 return data;
}

function securityHeaderSummary(headers){
 if(!headers||typeof headers!=='object')return'—';
 const keys=Object.keys(headers).filter(k=>headers[k]);
 return keys.length?keys.join(', '):'None';
}

function serviceSummary(services){
 const rows=Array.isArray(services?.services)?services.services:[];
 if(!rows.length)return{checked:services?.checked_hosts??0,online:0,dnsOnly:0,notFound:0,summary:'—'};
 const online=rows.filter(x=>x.status==='online').length;
 const dnsOnly=rows.filter(x=>x.status==='dns_only').length;
 const notFound=rows.filter(x=>x.status==='not_found').length;
 return{checked:services?.checked_hosts??rows.length,online,dnsOnly,notFound,summary:rows.map(x=>(x.hostname||x.label||'unknown')+'='+String(x.status||'unknown').toUpperCase()).join('; ')};
}

function bulkValue(item){
 const a=item.audit||{},d=a.domain_audit||{},s=a.ssl_audit||{},wa=a.website_audit||{},http=wa.http||{},https=wa.https||{},i=a.ip_audit||{},e=a.email_audit||{},dns=a.dns_audit||{},p=a.provider_detection||{},sv=serviceSummary(a.service_discovery||{});
 const records=dns.records||{};
 return {
  domain:item.domain,status:item.status==='ok'?'OK':'ERROR',
  domainExpiry:d.expires_at||'N/A',domainDays:formatDays(d.days_remaining),domainSource:d.source||'—',
  http:http.status??'—',httpOnline:boolLabel(http.online),httpUrl:http.final_url||'—',httpResponse:http.response_time_ms??'—',httpType:http.content_type||'—',httpServer:http.server||'—',httpHsts:boolLabel(http.hsts),
  https:https.status??'—',httpsOnline:boolLabel(https.online),httpsUrl:https.final_url||'—',httpsResponse:https.response_time_ms??'—',httpsType:https.content_type||'—',httpsServer:https.server||'—',httpsHsts:boolLabel(https.hsts),transport:boolLabel(https.transport_verified),
  sslVendor:s.vendor||'N/A',sslSubject:s.subject||'—',sslIssuer:s.issuer||'—',sslFrom:s.valid_from||'—',sslExpiry:s.valid_to||'N/A',sslDays:formatDays(s.days_remaining),hostname:s.verify?'YES':'NO',tls:s.tls_version||'—',cipher:s.cipher||'—',san:(s.san||[]).join(', ')||'—',
  resolvedIpv4:(a.resolved_ipv4||[]).join(', ')||'—',ip:i.ip||'N/A',asn:i.asn||'—',network:i.network||'—',organization:i.organization||'—',ipProvider:i.provider||'—',
  dnsProvider:dns.dns_provider||p.dns_provider||'N/A',nameservers:(dns.dns_nameservers||[]).join(', ')||'N/A',dnssec:dns.dnssec?'DETECTED':'NOT DETECTED',ipv4:(records.A||[]).length,ipv6:(records.AAAA||[]).length,ns:(records.NS||[]).length,mx:(records.MX||[]).length,dnsTypes:(dns.record_types_found||Object.keys(records).filter(k=>(records[k]||[]).length)).join(', ')||'—',dnsRecordCount:Object.values(records).reduce((n,v)=>n+(Array.isArray(v)?v.length:0),0),
  mail:e.provider||'N/A',spf:e.spf_present?(e.spf||'PASS'):'MISSING',dmarc:e.dmarc_present?(e.dmarc||'PASS'):'MISSING',dkim:Object.entries(e.dkim||{}).map(([k,v])=>k+': '+(v.present?'PASS':'MISSING')).join('; ')||'Not checked',mta:e.mta_sts_present?(e.mta_sts||'PASS'):'MISSING',tlsRpt:e.tls_rpt_present?(e.tls_rpt||'PASS'):'MISSING',
  cdn:p.cdn||'—',waf:p.waf||'—',hosting:p.hosting_provider||'—',servicesChecked:sv.checked,servicesOnline:sv.online,servicesDnsOnly:sv.dnsOnly,servicesNotFound:sv.notFound,serviceSummary:sv.summary,
  error:item.error||''
 };
}

function bulkResultRow(item){
 const v=bulkValue(item),ok=v.status==='OK';
 const td=(value,cls='')=>`<td class="${cls}">${esc(value)}</td>`;
 return `<tr>${td(v.domain)}${td(v.status,ok?'status-ok':'status-error')}${td(v.domainExpiry)}${td(v.domainDays)}${td(v.domainSource)}${td(v.http)}${td(v.httpOnline,v.httpOnline==='YES'?'status-online':'')}${td(v.httpUrl,'wrap')}${td(v.httpResponse)}${td(v.httpType)}${td(v.httpServer)}${td(v.httpHsts)}${td(v.https)}${td(v.httpsOnline,v.httpsOnline==='YES'?'status-online':'')}${td(v.httpsUrl,'wrap')}${td(v.httpsResponse)}${td(v.httpsType)}${td(v.httpsServer)}${td(v.httpsHsts)}${td(v.transport)}${td(v.sslVendor)}${td(v.sslSubject,'wrap')}${td(v.sslIssuer,'wrap')}${td(v.sslFrom)}${td(v.sslExpiry)}${td(v.sslDays)}${td(v.hostname)}${td(v.tls)}${td(v.cipher)}${td(v.san,'wrap')}${td(v.resolvedIpv4,'wrap')}${td(v.ip)}${td(v.asn)}${td(v.network)}${td(v.organization,'wrap')}${td(v.ipProvider)}${td(v.dnsProvider)}${td(v.nameservers,'wrap')}${td(v.dnssec)}${td(v.ipv4)}${td(v.ipv6)}${td(v.ns)}${td(v.mx)}${td(v.dnsTypes)}${td(v.dnsRecordCount)}${td(v.mail)}${td(v.spf,'wrap')}${td(v.dmarc,'wrap')}${td(v.dkim,'wrap')}${td(v.mta)}${td(v.tlsRpt)}${td(v.cdn)}${td(v.waf)}${td(v.hosting)}${td(v.servicesChecked)}${td(v.servicesOnline)}${td(v.servicesDnsOnly)}${td(v.servicesNotFound)}${td(v.serviceSummary,'wrap')}${td(v.error,'wrap')}</tr>`;
}

function renderBulkResults(target,results,total,progress){
 const groups=[
  ['Audit',2,'g-audit'],['Domain',3,'g-domain'],['Website / HTTP',7,'g-web'],['Website / HTTPS',8,'g-web'],['SSL / TLS',10,'g-ssl'],['IP / Hosting',6,'g-ip'],['DNS Summary',8,'g-dns'],['Email Security',6,'g-mail'],['Provider / Service Discovery',8,'g-provider'],['Error',1,'g-error']
 ];
 const headers=['Domain','Status','Domain Expiry','Domain Days','Domain Source','HTTP Status','HTTP Online','HTTP Final URL','HTTP Response ms','HTTP Content-Type','HTTP Server','HTTP HSTS','HTTPS Status','HTTPS Online','HTTPS Final URL','HTTPS Response ms','HTTPS Content-Type','HTTPS Server','HTTPS HSTS','TLS Transport Verified','SSL Vendor','SSL Subject','SSL Issuer','SSL Valid From','SSL Expiry','SSL Days','Hostname Match','TLS Version','Cipher','SAN','Resolved IPv4','Primary IP','ASN','Network','Organization','IP Provider','DNS Provider','Nameservers','DNSSEC','IPv4 Count','IPv6 Count','NS Count','MX Count','DNS Record Types','DNS Record Count','Mail Provider','SPF','DMARC','DKIM','MTA-STS','TLS-RPT','CDN','WAF','Hosting Provider','Services Checked','Services Online','Services DNS Only','Services Not Found','Service Summary','Error'];
 const groupCells=groups.map(g=>`<th colspan="${g[1]}" class="${g[2]}">${esc(g[0])}</th>`).join('');
 const rows=results.map(bulkResultRow).join('');
 target.innerHTML=`<div class="card"><h3 class="it-bulk-section-title">Bulk Result</h3><div class="it-bulk-meta"><span class="it-bulk-pill"><strong>${results.length}</strong>&nbsp;/&nbsp;${total} completed</span><span class="it-bulk-pill">Progress: ${progress}%</span><span class="it-bulk-pill">DNS: summary only</span></div><div class="it-bulk-progress"><div style="width:${progress}%"></div></div><div class="it-bulk-table-wrap"><table class="it-bulk-table"><thead><tr class="group-row">${groupCells}</tr><tr class="header-row">${headers.map(h=>`<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows}</tbody></table></div><p class="muted" style="margin-top:10px">Bulk Summary contains the same audit categories as Single Domain Audit. Individual DNS records are intentionally omitted; DNS counts, provider, nameservers, DNSSEC and record types are summarized.</p></div>`;
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
