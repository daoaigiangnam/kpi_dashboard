@extends('layouts.admin')
@section('title','IT Outsourcing Tools')
@section('content')
<style>
.it-bulk-card{overflow:hidden}
.it-bulk-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.it-bulk-progress{height:9px;background:#e9eef3;border-radius:99px;overflow:hidden;margin:10px 0}
.it-bulk-progress>div{height:100%;width:0;transition:width .25s ease}
.it-bulk-table-wrap{overflow:auto;border:1px solid #dfe5ea;border-radius:10px;margin-top:14px;max-height:620px;background:#fff}
.it-bulk-table{width:max-content;min-width:100%;border-collapse:separate;border-spacing:0;font-size:12px;background:#fff}
.it-bulk-table th,.it-bulk-table td{padding:8px 10px;border-right:1px solid #e5e9ed;border-bottom:1px solid #e5e9ed;vertical-align:top;white-space:nowrap}
.it-bulk-table thead tr:first-child th{position:sticky;top:0;background:#dfe8ee;font-weight:800;text-align:center;z-index:5}
.it-bulk-table thead tr:nth-child(2) th{position:sticky;top:33px;background:#f3f6f8;font-weight:700;white-space:nowrap;z-index:4}
.it-bulk-table td.wrap{white-space:normal;min-width:180px;max-width:360px;word-break:break-word}
.it-bulk-table th:first-child,.it-bulk-table td:first-child{position:sticky;left:0;background:#fff;z-index:6;min-width:180px}
.it-bulk-table th:first-child{background:#dfe8ee}
.it-bulk-table th:nth-child(2),.it-bulk-table td:nth-child(2){position:sticky;left:180px;background:#fff;z-index:6}
.it-bulk-table th:nth-child(2){background:#f3f6f8}
.it-bulk-table tr:hover td{background:#f8fbfd}
.it-bulk-status-ok{font-weight:800}
.it-bulk-status-error{font-weight:800}
.it-bulk-section-title{margin:0 0 4px}
.it-bulk-note{margin-top:10px;font-size:12px}
.it-bulk-count{display:inline-block;padding:3px 8px;border:1px solid #d7e0e6;border-radius:999px;background:#f7fafb;margin-left:6px}
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

function bulkValue(item){const a=item.audit||{},d=a.domain_audit||{},s=a.ssl_audit||{},w=(a.website_audit||{}).https||{},i=a.ip_audit||{},e=a.email_audit||{},dns=a.dns_audit||{},p=a.provider_detection||{},svc=a.service_discovery||{};const records=dns.records||{};const types=(dns.record_types_found||Object.keys(records).filter(k=>(records[k]||[]).length));const dkim=Object.entries(e.dkim||{}).map(([k,v])=>k+': '+(v.present?'PASS':'MISSING')).join('; ')||'Not checked';const serviceSummary=Array.isArray(svc.services)?svc.services.filter(x=>x&&x.status==='online').map(x=>x.label||x.hostname).join(', '):'';return {checkedAt:a.checked_at||'—',domain:item.domain,status:item.status==='ok'?'OK':'ERROR',wanIp:item.wan_ip||a.wan_ip_supplied||'—',duration:a.duration_ms??'—',domainStatus:d.status||'—',domainExpiry:d.expires_at||'N/A',domainDays:formatDays(d.days_remaining),domainSource:d.source||'—',website:w.online?'ONLINE':(w.status?'UNREACHABLE':'OFFLINE'),https:w.status??'—',response:w.response_time_ms??'—',finalUrl:w.final_url||'—',sslVendor:s.vendor||'N/A',sslSubject:s.subject||'—',sslIssuer:s.issuer||'—',sslFrom:s.valid_from||'—',sslExpiry:s.valid_to||'N/A',sslDays:formatDays(s.days_remaining),hostname:s.verify?'YES':'NO',tls:s.tls_version||'—',cipher:s.cipher||'—',ip:i.ip||'N/A',resolvedIpv4:(a.resolved_ipv4||[]).join(', ')||'—',asn:i.asn||'—',network:i.network||i.organization||i.provider||'N/A',organization:i.organization||'—',ipProvider:i.provider||'—',dnsProvider:dns.dns_provider||p.dns_provider||'N/A',nameservers:(dns.dns_nameservers||[]).join(', ')||'N/A',dnssec:dns.dnssec?'DETECTED':'NOT DETECTED',ipv4Count:(records.A||[]).length,ipv6Count:(records.AAAA||[]).length,nsCount:(records.NS||[]).length,mxCount:(records.MX||[]).length,dnsTypes:types.join(', ')||'None',mail:e.provider||'N/A',spf:e.spf_present?'PASS':'MISSING',dmarc:e.dmarc_present?'PASS':'MISSING',dkim,mta:e.mta_sts_present?'PASS':'MISSING',tlsRpt:e.tls_rpt_present?'PASS':'MISSING',cdn:p.cdn||'—',waf:p.waf||'—',hosting:p.hosting_provider||'—',services:serviceSummary||`${svc.checked_hosts||0} hosts checked`,error:item.error||a.error||''};}

function bulkResultRow(item){const v=bulkValue(item);const c=(x)=>esc(x);return `<tr><td>${c(v.domain)}</td><td class="${v.status==='OK'?'it-bulk-status-ok':'it-bulk-status-error'}">${c(v.status)}</td><td>${c(v.checkedAt)}</td><td>${c(v.wanIp)}</td><td>${c(v.duration)}</td><td>${c(v.domainStatus)}</td><td>${c(v.domainExpiry)}</td><td>${c(v.domainDays)}</td><td>${c(v.domainSource)}</td><td>${c(v.website)}</td><td>${c(v.https)}</td><td>${c(v.response)}</td><td class="wrap">${c(v.finalUrl)}</td><td>${c(v.sslVendor)}</td><td class="wrap">${c(v.sslSubject)}</td><td class="wrap">${c(v.sslIssuer)}</td><td>${c(v.sslFrom)}</td><td>${c(v.sslExpiry)}</td><td>${c(v.sslDays)}</td><td>${c(v.hostname)}</td><td>${c(v.tls)}</td><td class="wrap">${c(v.cipher)}</td><td>${c(v.ip)}</td><td class="wrap">${c(v.resolvedIpv4)}</td><td>${c(v.asn)}</td><td>${c(v.network)}</td><td class="wrap">${c(v.organization)}</td><td>${c(v.ipProvider)}</td><td>${c(v.dnsProvider)}</td><td class="wrap">${c(v.nameservers)}</td><td>${c(v.dnssec)}</td><td>${c(v.ipv4Count)}</td><td>${c(v.ipv6Count)}</td><td>${c(v.nsCount)}</td><td>${c(v.mxCount)}</td><td class="wrap">${c(v.dnsTypes)}</td><td>${c(v.mail)}</td><td class="wrap">${c(v.spf)}</td><td class="wrap">${c(v.dmarc)}</td><td class="wrap">${c(v.dkim)}</td><td>${c(v.mta)}</td><td>${c(v.tlsRpt)}</td><td>${c(v.cdn)}</td><td>${c(v.waf)}</td><td>${c(v.hosting)}</td><td class="wrap">${c(v.services)}</td><td class="wrap">${c(v.error)}</td></tr>`;}

function renderBulkResults(target,results,total,progress){const groups=[['Audit',5],['Domain',4],['Website',4],['SSL / TLS',9],['IP / Network',6],['DNS Summary',8],['Email Security',6],['Infrastructure',5],['Error',1]];const headers=['Domain','Status','Checked At','WAN IP','Duration ms','Domain Status','Domain Expiry','Domain Days','Domain Source','Website','HTTPS','Response ms','Final URL','SSL Vendor','SSL Subject','SSL Issuer','SSL Valid From','SSL Expiry','SSL Days','Hostname Match','TLS Version','Cipher','IP','Resolved IPv4','ASN','Network','Organization','IP Provider','DNS Provider','Nameservers','DNSSEC','IPv4','IPv6','NS','MX','Record Types','Mail Provider','SPF','DMARC','DKIM','MTA-STS','TLS-RPT','CDN','WAF','Hosting Provider','Services','Error'];const rows=results.map(bulkResultRow).join('');let groupHtml='';let pos=0;groups.forEach(([name,count])=>{groupHtml+=`<th colspan="${count}">${esc(name)}</th>`;pos+=count;});const headHtml=headers.map(h=>`<th>${esc(h)}</th>`).join('');target.innerHTML=`<div class="card"><h3 class="it-bulk-section-title">Bulk Result <span class="it-bulk-count">${results.length} / ${total}</span></h3><p><strong>${results.length} / ${total}</strong> completed</p><div class="it-bulk-progress"><div style="width:${progress}%"></div></div><div class="it-bulk-table-wrap"><table class="it-bulk-table"><thead><tr>${groupHtml}</tr><tr>${headHtml}</tr></thead><tbody>${rows}</tbody></table></div><p class="muted it-bulk-note">Summary contains the same audit categories as Single Domain Audit. Individual DNS Records are intentionally excluded; DNS is represented by provider, nameservers, DNSSEC, counts and detected record types.</p></div>`;}

async function runBulk(items){const target=document.getElementById('bulk-result');const button=document.getElementById('bulk-run');if(!items.length){target.innerHTML='<div class="card">Please enter at least one domain.</div>';return;}button.disabled=true;button.textContent='Running…';const results=[];target.innerHTML=`<div class="card"><h3>Bulk Audit</h3><p>0 / ${items.length} completed</p><div class="it-bulk-progress"><div></div></div><p id="bulk-current" class="muted">Preparing…</p></div>`;try{for(let index=0;index<items.length;index++){const item=items[index],current=document.getElementById('bulk-current');if(current)current.textContent=`[${index+1}/${items.length}] Checking ${item.domain}…`;try{const audit=await requestAudit(item);results.push({status:'ok',domain:item.domain,wan_ip:item.wan_ip||null,audit});}catch(error){results.push({status:'error',domain:item.domain,wan_ip:item.wan_ip||null,error:error.message});}renderBulkResults(target,results,items.length,Math.round(((index+1)/items.length)*100));}const title=target.querySelector('.it-bulk-section-title');if(title)title.insertAdjacentHTML('beforebegin','<p><strong>Bulk audit completed.</strong></p>');}finally{button.disabled=false;button.textContent='Run Bulk Audit';}}

async function runBulkFromTextarea(){const lines=document.getElementById('bulk-items').value.split(/\r?\n/).map(v=>v.trim()).filter(Boolean).slice(0,100);const items=lines.map(line=>{try{if(line.startsWith('{'))return JSON.parse(line);}catch(_){}const p=line.split(',').map(v=>v.trim());return{domain:p[0],wan_ip:p[1]||null};});await runBulk(items);}

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