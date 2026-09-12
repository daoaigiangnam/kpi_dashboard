<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KPI Dashboard Admin</title>
    <style>
        *{box-sizing:border-box} body{margin:0;font-family:Inter,Arial,sans-serif;background:#f6f9f7;color:#17231c}
        .nav{width:250px;position:fixed;inset:0 auto 0 0;background:#123b2a;color:#fff;padding:18px 14px;overflow-y:auto;z-index:100}.brand{font-size:20px;font-weight:700;margin:4px 8px 22px}.nav-toggle{display:none}.nav-links>a{display:block;color:#dcebe2;text-decoration:none;padding:10px 12px;border-radius:7px;margin:3px 0}.nav-links>a:hover,.nav-links>a.active{background:#2f8f5b;color:#fff}
        .nav-group{margin:6px 0;border:1px solid rgba(255,255,255,.08);border-radius:8px;overflow:hidden}.nav-group summary{list-style:none;cursor:pointer;padding:10px 12px;color:#fff;font-weight:700;background:rgba(255,255,255,.045);user-select:none}.nav-group summary::-webkit-details-marker{display:none}.nav-group summary:before{content:'›';display:inline-block;width:18px;font-size:18px;line-height:10px;vertical-align:-1px;transition:transform .15s}.nav-group[open] summary:before{transform:rotate(90deg)}.nav-group summary:hover{background:#1b5139}.nav-sub{padding:3px 5px 5px;background:rgba(0,0,0,.08)}.nav-sub a{display:block;color:#dcebe2;text-decoration:none;padding:8px 10px 8px 28px;border-radius:6px;margin:2px 0;font-size:13px}.nav-sub a:hover,.nav-sub a.active{background:#2f8f5b;color:#fff}
        .main{margin-left:250px;padding:28px;min-width:0}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px}.card{background:#fff;border:1px solid #e1e9e4;border-radius:10px;padding:20px;box-shadow:0 2px 8px #123b2a0a;min-width:0}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}.table{width:100%;min-width:680px;border-collapse:collapse}.table th,.table td{padding:12px;border-bottom:1px solid #e8eee9;text-align:left;vertical-align:middle}.table th{white-space:nowrap}.table td:last-child{white-space:nowrap}.actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.btn{display:inline-block;border:0;border-radius:7px;padding:9px 14px;text-decoration:none;cursor:pointer;background:#2e8b57;color:#fff;font-size:14px}.btn:hover{background:#267349}.btn.gray{background:#64748b}.btn.gray:hover{background:#475569}.btn.red{background:#d9534f}.btn.red:hover{background:#c43d39}.form{max-width:720px;width:100%}.input{width:100%;padding:10px;border:1px solid #cfdad3;border-radius:7px;margin-top:5px;background:#fff}.input:focus{outline:none;border-color:#2e8b57;box-shadow:0 0 0 3px #2e8b571a}.field{margin-bottom:15px}.alert{padding:12px;background:#e8f6ed;color:#24613f;border:1px solid #cce9d6;border-radius:7px;margin-bottom:15px}.warning{padding:12px;background:#fff7cc;color:#735b00;border:1px solid #ead27a;border-radius:7px;margin-bottom:15px}.info{padding:12px;background:#eef6ff;color:#24527a;border:1px solid #c9dff5;border-radius:7px;margin-bottom:15px}.error{padding:12px;background:#fcebea;color:#8f2f2c;border:1px solid #f4c9c7;border-radius:7px;margin-bottom:15px}.muted{color:#66736b;font-size:13px}
        .pagination{display:flex;align-items:center;justify-content:flex-end;gap:6px;margin-top:16px;padding:8px 0;min-height:40px}.pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border:1px solid #d7e1da;border-radius:7px;background:#fff;color:#334155;text-decoration:none;font-size:13px;line-height:1}.pagination a:hover{background:#eef6f1;border-color:#b9cdbf}.pagination span[aria-current="page"]{background:#2e8b57;color:#fff;border-color:#2e8b57;font-weight:700}.pagination span[aria-disabled="true"]{color:#a3ada7;background:#f5f7f6;cursor:not-allowed}.pagination svg{width:16px!important;height:16px!important;display:block}.pagination p{margin:0;font-size:12px;color:#66736b}.pagination .hidden{display:inline-flex}
        nav[role="navigation"][aria-label*="Pagination"]{display:flex!important;align-items:center!important;justify-content:flex-end!important;gap:6px!important;flex-wrap:wrap!important;margin-top:16px!important;padding:8px 0!important;min-height:40px!important;font-size:13px!important}
        nav[role="navigation"][aria-label*="Pagination"] svg{width:16px!important;height:16px!important;max-width:16px!important;max-height:16px!important;display:block!important}
        nav[role="navigation"][aria-label*="Pagination"] a,nav[role="navigation"][aria-label*="Pagination"] span{font-size:13px!important;line-height:1.2!important}
        nav[role="navigation"][aria-label*="Pagination"] a{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-width:34px!important;height:34px!important;padding:0 10px!important;border:1px solid #d7e1da!important;border-radius:7px!important;background:#fff!important;color:#334155!important;text-decoration:none!important}
        nav[role="navigation"][aria-label*="Pagination"] span[aria-current="page"]{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-width:34px!important;height:34px!important;padding:0 10px!important;border:1px solid #2e8b57!important;border-radius:7px!important;background:#2e8b57!important;color:#fff!important;font-weight:700!important}
        nav[role="navigation"][aria-label*="Pagination"] a:hover{background:#eef6f1!important;border-color:#b9cdbf!important}
        nav[role="navigation"][aria-label*="Pagination"] span[aria-disabled="true"]{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-width:34px!important;height:34px!important;padding:0 10px!important;border:1px solid #d7e1da!important;border-radius:7px!important;background:#f5f7f6!important;color:#a3ada7!important}
        nav[role="navigation"][aria-label*="Pagination"] p{margin:0!important;font-size:12px!important;color:#66736b!important}
        nav[role="navigation"][aria-label*="Pagination"] .hidden{display:flex!important}
        @media(max-width:900px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}} @media(max-width:700px){.nav{position:fixed;left:0;top:0;bottom:auto;width:100%;height:auto;padding:14px 16px;overflow:visible}.brand{margin:0;display:inline-block;line-height:40px}.nav-toggle{display:block;position:absolute;right:16px;top:14px;width:40px;height:40px;border:0;border-radius:7px;background:#1d5b40;color:#fff;font-size:22px;cursor:pointer}.nav-toggle:hover{background:#2f8f5b}.nav-links{display:none;padding-top:10px}.nav.open .nav-links{display:block}.nav a{margin:2px 0;padding:9px 10px}.nav-group{margin:4px 0}.nav-group summary{padding:9px 10px}.nav-sub a{padding:8px 10px 8px 24px}.nav form{margin-top:12px!important;padding-bottom:2px}.main{margin-left:0;padding:78px 16px 24px}.top{margin-bottom:16px}.top h1{font-size:28px!important;line-height:1.2}.grid{grid-template-columns:1fr;gap:12px}.card{padding:16px}.pagination{justify-content:center;flex-wrap:wrap}nav[role="navigation"][aria-label*="Pagination"]{justify-content:center!important}}
    </style>
</head>
<body>
<aside class="nav" id="adminNav">
    <div class="brand">KPI Dashboard System</div>
    <button class="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" onclick="toggleAdminNav()">☰</button>
    <div class="nav-links">
        @if(auth()->user()->hasPermission('admin.view'))<a href="{{ route('admin.dashboard') }}">Dashboard</a>@endif

        @if(auth()->user()->hasPermission('users.view') || auth()->user()->hasPermission('groups.view') || auth()->user()->hasPermission('job_titles.view') || auth()->user()->hasPermission('departments.view') || auth()->user()->hasPermission('units.view') || auth()->user()->hasPermission('system.settings'))
            <details class="nav-group" open>
                <summary>⚙ Administration</summary>
                <div class="nav-sub">
                    @if(auth()->user()->hasPermission('users.view'))<a href="{{ route('admin.users.index') }}">Users</a>@endif
                    @if(auth()->user()->hasPermission('users.view') && auth()->user()->isSuperAdmin())<a href="{{ route('admin.users.pending') }}">Pending Registrations</a>@endif
                    @if(auth()->user()->hasPermission('groups.view'))<a href="{{ route('admin.groups.index') }}">User Groups</a>@endif
                    @if(auth()->user()->hasPermission('job_titles.view'))<a href="{{ route('admin.job_titles.index') }}">Job Titles</a>@endif
                    @if(auth()->user()->hasPermission('departments.view'))<a href="{{ route('admin.departments.index') }}">Departments</a>@endif
                    @if(auth()->user()->hasPermission('units.view'))<a href="{{ route('admin.units.index') }}">Units</a>@endif
                    @if(auth()->user()->hasPermission('system.settings'))<a href="{{ route('admin.settings.index') }}">System Settings</a>@endif
                </div>
            </details>
        @endif

        @if(auth()->user()->hasPermission('kpi.parameters') || auth()->user()->hasPermission('kpi.tickets'))
            <details class="nav-group" open>
                <summary>📊 KPI Management</summary>
                <div class="nav-sub">
                    @if(auth()->user()->hasPermission('kpi.parameters'))<a href="{{ route('admin.kpi_parameters.index') }}">KPI Parameters</a>@endif
                    @if(auth()->user()->hasPermission('kpi.tickets'))<a href="{{ route('admin.tickets.index') }}">Ticket Data</a>@endif
                </div>
            </details>
        @endif

        @if(auth()->user()->hasPermission('it_tools.view'))
            <details class="nav-group" open>
                <summary>🛠 IT Tools</summary>
                <div class="nav-sub">
                    <a href="{{ route('admin.it_tools.dashboard') }}">IT Tools Dashboard</a>
                    <a href="{{ route('admin.it_tools.index') }}">Check Domain</a>
                    <a href="{{ route('admin.it_tools.history') }}">Audit History</a>
                </div>
            </details>
        @endif

        <a href="{{ route('account.index') }}">👤 Account Information</a>
        <form method="post" action="{{ route('logout') }}" style="margin-top:18px">@csrf<button class="btn gray" type="submit">Logout</button></form>
    </div>
</aside>
<main class="main"><div class="top"><div><h1 style="margin:0">@yield('title')</h1><div class="muted">{{ auth()->user()->name }} · {{ auth()->user()->group?->name }}</div></div></div>
@if(session('success'))<div class="alert">{{ session('success') }}</div>@endif @if(session('info'))<div class="info">{{ session('info') }}</div>@endif @if(session('warning'))<div class="warning">{{ session('warning') }}</div>@endif
@if($errors->any())<div class="error"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@yield('content')</main>
<script>function toggleAdminNav(){const nav=document.getElementById('adminNav');const button=nav.querySelector('.nav-toggle');const open=nav.classList.toggle('open');button.setAttribute('aria-expanded',open?'true':'false');}</script>
</body></html>
