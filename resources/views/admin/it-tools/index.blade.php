@extends('layouts.admin')
@section('title','IT Outsourcing Tools')
@section('content')
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

<div class="card" style="margin-top:20px">
    <h3>Bulk Audit</h3>
    <p class="muted">Paste one domain per line, or upload the Excel/CSV template.</p>
    <textarea id="bulk-items" rows="8" style="width:100%;font-family:monospace" placeholder="example.com\nexample.vn,1.2.3.4"></textarea>
    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <button id="bulk-run" type="button">Run Bulk Audit</button>
        <input id="bulk-file" type="file" accept=".xlsx,.xls,.csv,.txt">
        <button id="bulk-import" type="button">Import File</button>
        <span id="bulk-import-info" class="muted"></span>
    </div>
</div>

<div id="result" style="margin-top:20px"></div>
<div id="bulk-result" style="margin-top:20px"></div>

<script>
const csrf = document.querySelector('[name=_token]').value;
const esc = (value) => String(value ?? '').replace(/[&<>\"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[c]));

async function runBulk(items) {
    const target = document.getElementById('bulk-result');
    if (!items.length) { target.innerHTML='<div class="card">Please enter at least one domain.</div>'; return; }
    target.innerHTML='<div class="card">Bulk audit running…</div>';
    try {
        const response = await fetch('{{ route('admin.it_tools.bulk_audit') }}', {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},
            body:JSON.stringify({items})
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Bulk audit failed');
        const rows = (data.results || []).map(item => {
            const audit = item.audit || {};
            const d = audit.domain_audit || {};
            const s = audit.ssl_audit || {};
            const w = (audit.website_audit || {}).https || {};
            const i = audit.ip_audit || {};
            const e = audit.email_audit || {};
            return `<tr><td>${esc(item.domain)}</td><td>${esc(d.days_remaining)}</td><td>${esc(s.vendor)}</td><td>${esc(s.days_remaining)}</td><td>${esc(w.status)}</td><td>${w.online ? 'ONLINE':'OFFLINE'}</td><td>${esc(i.network || i.organization || i.provider)}</td><td>${esc(e.provider)}</td></tr>`;
        }).join('');
        target.innerHTML = `<div class="card"><h3>Bulk Result</h3><p>${esc(data.processed)} processed / ${esc(data.requested)} requested</p><div style="overflow:auto"><table><thead><tr><th>Domain</th><th>Domain days</th><th>SSL Vendor</th><th>SSL days</th><th>HTTPS</th><th>Web</th><th>IP Network</th><th>Mail</th></tr></thead><tbody>${rows}</tbody></table></div></div>`;
    } catch (error) {
        target.innerHTML='<div class="card">Bulk audit failed: '+esc(error.message)+'</div>';
    }
}

document.getElementById('audit-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const result = document.getElementById('result');
    result.innerHTML = '<div class="card">Checking…</div>';
    try {
        const response = await fetch('{{ route('admin.it_tools.audit') }}', {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},
            body:JSON.stringify({
                domain:document.getElementById('domain').value,
                wan_ip:document.getElementById('wan_ip').value || null,
                dkim_selectors:document.getElementById('dkim_selectors').value || null
            })
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Audit failed');
        renderAudit(result, data);
    } catch (error) {
        result.innerHTML = '<div class="card">Audit failed: '+esc(error.message)+'</div>';
    }
});

document.getElementById('bulk-run').addEventListener('click', async () => {
    const lines = document.getElementById('bulk-items').value.split(/\r?\n/).map(v => v.trim()).filter(Boolean).slice(0,100);
    const items = lines.map(line => {
        try { if (line.startsWith('{')) return JSON.parse(line); } catch (_) {}
        const parts = line.split(',').map(v => v.trim());
        return {domain: parts[0], wan_ip: parts[1] || null};
    });
    await runBulk(items);
});

document.getElementById('bulk-import').addEventListener('click', async () => {
    const file = document.getElementById('bulk-file').files[0];
    const info = document.getElementById('bulk-import-info');
    if (!file) { info.textContent = 'Choose an Excel/CSV file first.'; return; }
    info.textContent = 'Importing…';
    try {
        const form = new FormData(); form.append('file', file);
        const response = await fetch('{{ route('admin.it_tools.bulk_import') }}', {method:'POST', headers:{'X-CSRF-TOKEN':csrf}, body:form});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Import failed');
        document.getElementById('bulk-items').value = (data.items || []).map(item => `${item.domain}${item.wan_ip ? ','+item.wan_ip : ''}`).join('\n');
        info.textContent = `${data.count} rows imported. Review the list, then click Run Bulk Audit.`;
    } catch (error) { info.textContent = 'Import failed: '+error.message; }
});

function statusLabel(online, status) {
    if (online) return 'ONLINE';
    if (status) return 'UNREACHABLE';
    return 'OFFLINE';
}

function renderAudit(target, data) {
    const d=data.domain_audit||{}, s=data.ssl_audit||{}, w=data.website_audit||{}, i=data.ip_audit||{}, e=data.email_audit||{}, dns=data.dns_audit||{};
    const https=w.https||{};
    const nsCount=(dns.records?.NS||[]).length;
    const aCount=(dns.records?.A||[]).length;
    const aaaaCount=(dns.records?.AAAA||[]).length;
    const mxCount=(dns.records?.MX||[]).length;
    const dnsLabel=dns.dns_provider || (nsCount ? 'Unknown provider' : 'No NS records');
    const ipNetwork=i.network || i.organization || i.provider || 'Provider unavailable';
    const domainExpiry=d.expires_at || (d.status === 'unavailable' ? 'Registry data unavailable' : 'N/A');
    const domainDays=d.days_remaining ?? 'N/A';
    const domainSource=d.source || '—';
    const ipMeta=[i.asn, i.organization].filter(Boolean).join(' · ');

    target.innerHTML = `<div class="grid">
        <div class="card"><div class="muted">Domain Expiry</div><h3>${esc(domainExpiry)}</h3><div>${esc(domainDays)}${typeof domainDays === 'number' ? ' days' : ''} · ${esc(domainSource)}</div></div>
        <div class="card"><div class="muted">SSL</div><h3>${esc(s.vendor || 'N/A')}</h3><div>${esc(s.valid_to || 'N/A')} · ${esc(s.days_remaining ?? 'N/A')} days</div></div>
        <div class="card"><div class="muted">Website</div><h3>${statusLabel(https.online, https.status)}</h3><div>HTTPS ${esc(https.status ?? '—')} · ${esc(https.response_time_ms ?? '—')} ms${https.transport_verified === false ? ' · TLS transport unverified' : ''}</div></div>
        <div class="card"><div class="muted">IP / Network</div><h3>${esc(i.ip || 'N/A')}</h3><div>${esc(ipNetwork)}${ipMeta ? '<br>'+esc(ipMeta) : ''}</div></div>
        <div class="card"><div class="muted">Email</div><h3>${esc(e.provider || 'Unknown provider')}</h3><div>SPF ${e.spf_present ? 'PASS':'MISSING'} · DMARC ${e.dmarc_present ? 'PASS':'MISSING'}</div></div>
        <div class="card"><div class="muted">DNS</div><h3>${esc(dnsLabel)}</h3><div>${aCount} IPv4 · ${aaaaCount} IPv6 · ${nsCount} NS · ${mxCount} MX</div></div>
    </div>
    <div class="card" style="margin-top:20px"><h3>Audit JSON</h3><pre style="white-space:pre-wrap">${esc(JSON.stringify(data,null,2))}</pre></div>`;
}
</script>
@endsection
