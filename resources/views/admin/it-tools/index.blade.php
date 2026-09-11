@extends('layouts.admin')
@section('title','IT Outsourcing Tools')
@section('content')
<div class="card">
    <h2>IT Outsourcing Tools</h2>
    <p class="muted">Domain, DNS, SSL/TLS, Website and IP audit.</p>
    <form id="audit-form" style="margin-top:16px">
        @csrf
        <div style="display:grid;grid-template-columns:2fr 1fr auto;gap:12px;align-items:end">
            <div><label>Domain</label><input id="domain" name="domain" required placeholder="example.com"></div>
            <div><label>WAN IP (optional)</label><input id="wan_ip" name="wan_ip" placeholder="1.2.3.4"></div>
            <button type="submit">Run Audit</button>
        </div>
    </form>
</div>
<div id="result" style="margin-top:20px"></div>
<script>
document.getElementById('audit-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const result = document.getElementById('result');
    result.innerHTML = '<div class="card">Checking…</div>';
    const response = await fetch('{{ route('admin.it_tools.audit') }}', {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('[name=_token]').value},
        body:JSON.stringify({domain:document.getElementById('domain').value,wan_ip:document.getElementById('wan_ip').value || null})
    });
    const data = await response.json();
    if (!response.ok) { result.innerHTML='<div class="card">Audit failed: '+(data.message || 'Unknown error')+'</div>'; return; }
    const d=data.domain_audit, s=data.ssl_audit, w=data.website_audit, i=data.ip_audit;
    result.innerHTML = `<div class="grid">
        <div class="card"><div class="muted">Domain Expiry</div><h3>${d.expires_at || 'N/A'}</h3><div>${d.days_remaining ?? 'N/A'} days</div></div>
        <div class="card"><div class="muted">SSL Vendor</div><h3>${s.vendor || 'N/A'}</h3><div>${s.valid_to || 'N/A'}</div></div>
        <div class="card"><div class="muted">Website</div><h3>${w.https.online ? 'ONLINE' : 'OFFLINE'}</h3><div>HTTPS ${w.https.status || '—'} · ${w.https.response_time_ms} ms</div></div>
        <div class="card"><div class="muted">IP / Provider</div><h3>${i?.ip || 'N/A'}</h3><div>${i?.organization || i?.provider || 'N/A'}</div></div>
    </div>
    <div class="card" style="margin-top:20px"><h3>Audit JSON</h3><pre style="white-space:pre-wrap">${JSON.stringify(data,null,2).replace(/</g,'&lt;')}</pre></div>`;
});
</script>
@endsection
