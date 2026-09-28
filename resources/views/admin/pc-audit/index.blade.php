@extends('layouts.admin')
@section('title','PC Audit')
@section('content')
<style>
    .pc-audit-page{--pa-primary:#0f766e;--pa-primary-dark:#115e59;--pa-danger:#dc2626;--pa-danger-dark:#b91c1c;--pa-soft:#ecfdf5;--pa-border:#e2e8f0;--pa-text:#0f172a;--pa-muted:#64748b}
    .pc-audit-page *{box-sizing:border-box}
    .pc-audit-hero{background:linear-gradient(135deg,#0f766e 0%,#155e75 100%);color:#fff;border-radius:18px;padding:24px 26px;margin-bottom:18px;box-shadow:0 10px 30px rgba(15,118,110,.16)}
    .pc-audit-hero-inner{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}
    .pc-audit-title{margin:0;font-size:25px;font-weight:750;letter-spacing:-.3px}
    .pc-audit-subtitle{margin:7px 0 0;color:rgba(255,255,255,.82);font-size:14px}
    .pc-audit-actions{display:flex;gap:8px;flex-wrap:wrap}
    .pc-audit-actions a{display:inline-flex;align-items:center;gap:6px;padding:9px 13px;border:1px solid rgba(255,255,255,.24);border-radius:9px;color:#fff;text-decoration:none;background:rgba(255,255,255,.11);font-size:13px;font-weight:650;transition:.15s}
    .pc-audit-actions a:hover{background:rgba(255,255,255,.2);transform:translateY(-1px)}
    .pc-audit-card{background:#fff;border:1px solid var(--pa-border);border-radius:16px;padding:18px 20px;box-shadow:0 5px 18px rgba(15,23,42,.045)}
    .pc-audit-toolbar{display:grid;grid-template-columns:minmax(280px,1fr) 240px auto auto;gap:12px;align-items:end}
    .pc-audit-field label{display:block;font-size:12px;font-weight:700;color:#475569;margin:0 0 6px}
    .pc-audit-field input,.pc-audit-field select{width:100%;height:40px;border:1px solid #cbd5e1;border-radius:9px;padding:0 12px;background:#fff;color:var(--pa-text);outline:none;transition:.15s}
    .pc-audit-field input:focus,.pc-audit-field select:focus{border-color:#14b8a6;box-shadow:0 0 0 3px rgba(20,184,166,.12)}
    .pc-audit-btn{height:40px;border:0;border-radius:9px;padding:0 15px;background:var(--pa-primary);color:#fff;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap}
    .pc-audit-btn:hover{background:var(--pa-primary-dark)}
    .pc-audit-btn.secondary{background:#fff;color:#334155;border:1px solid #cbd5e1}
    .pc-audit-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap}
    .pc-audit-section-title{margin:0;font-size:16px;font-weight:750;color:var(--pa-text)}
    .pc-audit-count{font-size:12px;color:var(--pa-muted);background:#f8fafc;border:1px solid var(--pa-border);padding:6px 10px;border-radius:999px}
    .pc-audit-table-wrap{overflow-x:auto;border:1px solid var(--pa-border);border-radius:12px}
    .pc-audit-table{width:100%;min-width:1220px;border-collapse:separate;border-spacing:0;font-size:13px}
    .pc-audit-table th{background:#f8fafc;color:#475569;font-size:11px;text-transform:uppercase;letter-spacing:.35px;font-weight:750;padding:12px 11px;border-bottom:1px solid var(--pa-border);white-space:nowrap;text-align:left}
    .pc-audit-table td{padding:13px 11px;border-bottom:1px solid #eef2f7;color:#334155;vertical-align:middle}
    .pc-audit-table tbody tr:last-child td{border-bottom:0}
    .pc-audit-table tbody tr:hover{background:#f8fffd}
    .pc-audit-check{width:18px;height:18px;accent-color:var(--pa-primary);cursor:pointer}
    .pc-audit-customer{font-weight:650;color:#0f172a;max-width:240px}
    .pc-audit-code{display:inline-flex;padding:5px 9px;border-radius:7px;background:#ecfdf5;color:#0f766e;font-weight:800;letter-spacing:.6px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
    .pc-audit-computer{font-weight:700;color:#0f172a}
    .pc-audit-serial{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:#475569}
    .pc-audit-status{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:800;letter-spacing:.25px}
    .pc-audit-status.pass{background:#dcfce7;color:#166534}.pc-audit-status.fail{background:#fee2e2;color:#b91c1c}.pc-audit-status.review{background:#fef3c7;color:#92400e}
    .pc-audit-score{font-weight:800;color:#0f766e}
    .pc-audit-row-actions{display:flex;align-items:center;gap:6px;white-space:nowrap}
    .pc-audit-view,.pc-audit-edit,.pc-audit-delete{display:inline-flex;align-items:center;justify-content:center;padding:7px 11px;border-radius:8px;text-decoration:none;font-weight:700;background:#fff;cursor:pointer}
    .pc-audit-view{border:1px solid #cbd5e1;color:#0f766e}.pc-audit-view:hover{background:#ecfdf5;border-color:#99f6e4}
    .pc-audit-edit{border:1px solid #bfdbfe;color:#2563eb}.pc-audit-edit:hover{background:#eff6ff;border-color:#93c5fd}
    .pc-audit-delete{border:1px solid #fecaca;color:var(--pa-danger);font:inherit}.pc-audit-delete:hover{background:#fef2f2;border-color:#fca5a5;color:var(--pa-danger-dark)}
    .pc-audit-export{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:14px}
    .pc-audit-export-note{font-size:12px;color:var(--pa-muted)}
    .pc-audit-empty{text-align:center!important;padding:38px!important;color:#94a3b8!important}
    .pc-audit-alert{display:flex;align-items:flex-start;gap:10px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:12px;padding:12px 14px;margin-bottom:18px;font-size:13px}
    .pc-audit-pagination{margin-top:16px}
    @media(max-width:900px){.pc-audit-toolbar{grid-template-columns:1fr 1fr}.pc-audit-toolbar .full{grid-column:1/-1}}
    @media(max-width:600px){.pc-audit-toolbar{grid-template-columns:1fr}.pc-audit-toolbar .full{grid-column:auto}.pc-audit-card{padding:14px}.pc-audit-hero{padding:20px}}
</style>

<div class="pc-audit-page">
    <div class="pc-audit-hero">
        <div class="pc-audit-hero-inner">
            <div>
                <h1 class="pc-audit-title">PC Audit</h1>
                <p class="pc-audit-subtitle">Quản lý, tra cứu và xuất báo cáo kiểm tra cấu hình máy tính tập trung.</p>
            </div>
            <div class="pc-audit-actions">
                @if(auth()->user()->hasPermission('pc_audit.codes'))<a href="{{ route('admin.pc_audit.codes') }}">🔑 Audit Code</a>@endif
                @if(auth()->user()->hasPermission('pc_audit.recipients'))<a href="{{ route('admin.pc_audit.recipients') }}">✉ Email Customer</a>@endif
                @if(auth()->user()->hasPermission('pc_audit.settings'))<a href="{{ route('admin.pc_audit.settings') }}">⚙ Cấu hình Tool</a>@endif
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="pc-audit-alert">✅ <span>{{ session('success') }}</span></div>
    @endif

    <div class="pc-audit-card">
        <form method="GET" action="{{ route('admin.pc_audit.index') }}" class="pc-audit-toolbar">
            <div class="pc-audit-field full">
                <label>Tìm kiếm</label>
                <input name="search" value="{{ $search }}" placeholder="Computer Name, Serial, Họ tên, Phòng ban hoặc Code...">
            </div>
            <div class="pc-audit-field">
                <label>Khách hàng</label>
                <select name="customer_id">
                    <option value="">Tất cả khách hàng</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string)$customerId===(string)$customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="pc-audit-btn" type="submit">🔍 Tìm kiếm</button>
            <a class="pc-audit-btn secondary" href="{{ route('admin.pc_audit.index') }}">Xóa lọc</a>
        </form>
    </div>

    <div class="pc-audit-card" style="margin-top:18px">
        <div class="pc-audit-section-head">
            <div>
                <h2 class="pc-audit-section-title">Danh sách máy đã Audit</h2>
                <div style="font-size:12px;color:#64748b;margin-top:4px">Chọn máy để xuất báo cáo Excel chi tiết.</div>
            </div>
            <div class="pc-audit-count">{{ $audits->total() }} bản ghi</div>
        </div>

        <form method="POST" action="{{ route('admin.pc_audit.export') }}" id="export-form">
            @csrf
            <div class="pc-audit-table-wrap">
                <table class="pc-audit-table">
                    <thead>
                        <tr>
                            <th style="width:42px"><input type="checkbox" class="pc-audit-check" id="check-all" title="Chọn tất cả"></th>
                            <th>Khách hàng</th><th>Chi nhánh</th><th>Code</th><th>Computer</th><th>Serial</th>
                            <th>Họ tên</th><th>Phòng ban</th><th>Kết quả</th><th>Điểm</th><th>Ngày Audit</th><th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($audits as $audit)
                        @php $status=$audit->audit_status ?: 'REVIEW'; $statusClass=strtolower($status); @endphp
                        <tr>
                            <td><input type="checkbox" class="pc-audit-check audit-check" name="ids[]" value="{{ $audit->id }}"></td>
                            <td><div class="pc-audit-customer">{{ $audit->auditCode?->branch?->customer?->name ?: '—' }}</div></td>
                            <td>{{ $audit->auditCode?->branch?->name ?: '—' }}</td>
                            <td><span class="pc-audit-code">{{ $audit->auditCode?->code ?: '—' }}</span></td>
                            <td><span class="pc-audit-computer">{{ $audit->computer_name ?: '—' }}</span></td>
                            <td><span class="pc-audit-serial">{{ $audit->serial_number ?: '—' }}</span></td>
                            <td>{{ $audit->employee_name ?: '—' }}</td>
                            <td>{{ $audit->department ?: '—' }}</td>
                            <td><span class="pc-audit-status {{ in_array($statusClass,['pass','fail','review']) ? $statusClass : 'review' }}">{{ $status }}</span></td>
                            <td><span class="pc-audit-score">{{ $audit->audit_score !== null ? $audit->audit_score.'%' : '—' }}</span></td>
                            <td>{{ optional($audit->collected_at)->format('d/m/Y H:i') ?: '—' }}</td>
                            <td>
                                <div class="pc-audit-row-actions">
                                    <a class="pc-audit-view" href="{{ route('admin.pc_audit.show', $audit) }}">Xem</a>
                                    @if(auth()->user()->hasPermission('pc_audit.edit'))
                                        <a class="pc-audit-edit" href="{{ route('admin.pc_audit.edit', $audit) }}">✏ Sửa</a>
                                    @endif
                                    @if(auth()->user()->hasPermission('pc_audit.delete'))
                                        <button class="pc-audit-delete" type="button" onclick="return submitDeletePcAudit(this, '{{ $audit->id }}', @js($audit->computer_name ?: 'máy này'));" title="Xóa vĩnh viễn PC Audit">🗑 Xóa</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="pc-audit-empty">Chưa có dữ liệu Audit phù hợp với điều kiện tìm kiếm.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pc-audit-export">
                @if(auth()->user()->hasPermission('pc_audit.export'))
                    <button class="pc-audit-btn" type="submit" id="export-button">📊 Xuất Excel các máy đã chọn</button>
                    <span class="pc-audit-export-note" id="selected-count">Chưa chọn máy nào · Tối đa 200 máy/lần · Mỗi máy một sheet đầy đủ.</span>
                @else
                    <span class="pc-audit-export-note">Bạn không có quyền xuất Excel PC Audit.</span>
                @endif
            </div>
        </form>

        <div class="pc-audit-pagination">{{ $audits->links() }}</div>
    </div>
</div>

<script>
function submitDeletePcAudit(button, auditId, computerName) {
    const confirmed = window.confirm(
        'XÓA VĨNH VIỄN PC AUDIT\n\n' +
        'Máy: ' + computerName + '\n\n' +
        'Thao tác này sẽ xóa toàn bộ dữ liệu Audit của máy, bao gồm phần cứng, lưu trữ, màn hình, GPU, pin, network, security, license và software.\n\n' +
        'Dữ liệu sẽ không được đánh dấu mà bị xóa hoàn toàn. Bạn có chắc chắn muốn tiếp tục?'
    );

    if (!confirmed) return false;

    const exportForm = document.getElementById('export-form');
    const csrf = exportForm ? exportForm.querySelector('input[name="_token"]')?.value : null;
    if (!csrf) {
        alert('Không tìm thấy CSRF token. Vui lòng tải lại trang.');
        return false;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = "{{ url('/admin/pc-audit') }}/" + encodeURIComponent(auditId);
    form.style.display = 'none';

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = csrf;
    form.appendChild(token);

    const method = document.createElement('input');
    method.type = 'hidden';
    method.name = '_method';
    method.value = 'DELETE';
    form.appendChild(method);

    document.body.appendChild(form);
    button.disabled = true;
    button.textContent = '⏳ Đang xóa...';
    form.submit();
    return false;
}

(function(){
    const all=document.getElementById('check-all');
    const checks=[...document.querySelectorAll('.audit-check')];
    const label=document.getElementById('selected-count');
    const exportForm=document.getElementById('export-form');
    function update(){
        if(!label) return;
        const n=checks.filter(c=>c.checked).length;
        label.textContent=n?`${n} máy đã chọn · Tối đa 200 máy/lần · Mỗi máy một sheet đầy đủ.`:'Chưa chọn máy nào · Tối đa 200 máy/lần · Mỗi máy một sheet đầy đủ.';
        if(all){all.checked=checks.length>0&&n===checks.length;all.indeterminate=n>0&&n<checks.length;}
    }
    if(all) all.addEventListener('change',()=>{checks.forEach(c=>c.checked=all.checked);update()});
    checks.forEach(c=>c.addEventListener('change',update));
    if(exportForm) exportForm.addEventListener('submit',function(e){
        if(!label) return;
        if(checks.filter(c=>c.checked).length===0){
            e.preventDefault();
            alert('Vui lòng chọn ít nhất 1 máy để xuất Excel.');
        }
    });
    update();
})();
</script>
@endsection
