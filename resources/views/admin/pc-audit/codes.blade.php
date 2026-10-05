@extends('layouts.admin')
@section('title','PC Audit - Audit Code')
@section('content')
<style>
    .pc-audit-hero{background:linear-gradient(135deg,#0f766e 0%,#0f4c5c 100%);color:#fff;border-radius:14px;padding:24px;box-shadow:0 10px 30px rgba(15,118,110,.16)}
    .pc-audit-hero h2{margin:0 0 7px;font-size:25px}.pc-audit-hero p{margin:0;opacity:.9;line-height:1.6}
    .pc-audit-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:22px;margin-top:18px;box-shadow:0 4px 18px rgba(15,23,42,.05)}
    .pc-audit-card h3{margin:0 0 6px;font-size:19px}.pc-audit-muted{color:#64748b;font-size:14px;line-height:1.55}
    .pc-audit-grid{display:grid;grid-template-columns:minmax(220px,1fr) minmax(220px,1fr) auto;gap:14px;align-items:end}
    .pc-audit-field label{display:block;font-weight:600;font-size:13px;color:#334155;margin-bottom:7px}
    .pc-audit-field select,.pc-audit-field input{width:100%;height:42px;padding:8px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;font-size:14px;box-sizing:border-box}
    .pc-audit-field select:focus,.pc-audit-field input:focus{outline:none;border-color:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.12)}
    .pc-audit-btn{height:42px;border:0;border-radius:8px;padding:0 17px;background:#0f766e;color:#fff;font-weight:600;cursor:pointer;white-space:nowrap}.pc-audit-btn:hover{background:#115e59}
    .pc-audit-btn.secondary{background:#f1f5f9;color:#334155;border:1px solid #cbd5e1}.pc-audit-alert{margin-top:16px;padding:12px 14px;border-radius:9px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
    .pc-audit-error{margin-top:16px;padding:12px 14px;border-radius:9px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b}.pc-audit-step{display:inline-flex;width:30px;height:30px;border-radius:50%;align-items:center;justify-content:center;background:#ccfbf1;color:#115e59;font-weight:700;margin-right:8px}
    .pc-audit-code-preview{margin-top:15px;padding:14px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:9px;color:#475569}.pc-audit-code-preview strong{color:#0f766e}
    .pc-audit-table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden}.pc-audit-table th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:.03em;text-align:left;padding:12px;border-bottom:1px solid #e2e8f0}
    .pc-audit-table td{padding:13px 12px;border-bottom:1px solid #eef2f7;font-size:14px;vertical-align:middle}.pc-audit-table tr:last-child td{border-bottom:0}
    .pc-audit-badge{display:inline-flex;align-items:center;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:600;background:#dcfce7;color:#166534}.pc-audit-badge.off{background:#f1f5f9;color:#64748b}
    .pc-audit-code{display:inline-flex;align-items:center;padding:7px 12px;border-radius:8px;background:#ecfdf5;color:#0f766e;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:18px;letter-spacing:2px;font-weight:800}
    @media(max-width:760px){.pc-audit-grid{grid-template-columns:1fr}.pc-audit-btn{width:100%}.pc-audit-card{padding:16px}.pc-audit-table{min-width:650px}.pc-audit-hero{padding:18px}}
</style>

<div class="pc-audit-hero">
    <h2>🖥️ PC Audit — Audit Code</h2>
    <p>Customer lấy trực tiếp từ <strong>IT Service</strong>. Chỉ cần khai báo <strong>Chi nhánh</strong>; hệ thống tự tạo <strong>mã 6 ký tự</strong> để User nhập trên máy.</p>
</div>

@if(session('success'))<div class="pc-audit-alert">✓ {{ session('success') }}</div>@endif
@if($errors->any())<div class="pc-audit-error">@foreach($errors->all() as $error)<div>• {{ $error }}</div>@endforeach</div>@endif

<div class="pc-audit-card">
    <h3><span class="pc-audit-step">1</span> Khai báo Chi nhánh</h3>
    <p class="pc-audit-muted">Customer và Email vẫn dùng dữ liệu của IT Service. Chỉ nhập tên Chi nhánh nếu Customer chưa có.</p>
    <form method="POST" action="{{ route('admin.pc_audit.codes.store') }}" style="margin-top:16px">
        @csrf<input type="hidden" name="action" value="create_branch">
        <div class="pc-audit-grid">
            <div class="pc-audit-field"><label>Customer</label><select name="customer_id" required><option value="">-- Chọn Customer --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string)old('customer_id',$customerId)===(string)$customer->id)>{{ $customer->name }}{{ $customer->code ? ' · '.$customer->code : '' }}</option>@endforeach</select></div>
            <div class="pc-audit-field"><label>Chi nhánh</label><input name="name" value="{{ old('name') }}" placeholder="Ví dụ: Hồ Chí Minh" required></div>
            <button class="pc-audit-btn" type="submit">+ Thêm Chi nhánh</button>
        </div>
    </form>
</div>

<div class="pc-audit-card">
    <h3><span class="pc-audit-step">2</span> Tạo Audit Code</h3>
    <p class="pc-audit-muted">Chọn Customer → Chi nhánh → <strong>Tạo Code</strong>. Code chỉ dài 6 ký tự, ví dụ <strong>A7K9P2</strong>.</p>
    <form method="POST" action="{{ route('admin.pc_audit.codes.store') }}" id="audit-code-form" style="margin-top:16px">
        @csrf
        <div class="pc-audit-grid">
            <div class="pc-audit-field"><label>Customer</label><select id="customer_id" required><option value="">-- Chọn Customer --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string)old('customer_id',$customerId)===(string)$customer->id)>{{ $customer->name }}{{ $customer->code ? ' · '.$customer->code : '' }}</option>@endforeach</select></div>
            <div class="pc-audit-field"><label>Chi nhánh</label><select name="branch_id" id="branch_id" required><option value="">-- Chọn Customer trước --</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" data-customer="{{ $branch->customer_id }}" @selected((string)old('branch_id')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            <button class="pc-audit-btn" type="submit">⚡ Tạo Code</button>
        </div>
    </form>
    <div class="pc-audit-code-preview"><strong>Code ngắn:</strong> 6 ký tự, chỉ dùng chữ in hoa và số, bỏ các ký tự dễ nhầm như <strong>O/0</strong> và <strong>I/1</strong>.<br>Ví dụ: <strong>A7K9P2</strong>. User chỉ cần nhập mã này vào PC Audit Tool.</div>
</div>

<div class="pc-audit-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><div><h3>📋 Danh sách Audit Code</h3><div class="pc-audit-muted">Mỗi Code được gắn với một Chi nhánh.</div></div>@if(auth()->user()->hasPermission('pc_audit.settings'))<a class="pc-audit-btn secondary" href="{{ route('admin.pc_audit.settings') }}" style="display:inline-flex;align-items:center;text-decoration:none">⚙ Cấu hình API</a>@endif</div>
    <form method="GET" style="display:grid;grid-template-columns:minmax(220px,1fr) minmax(240px,1.4fr) auto auto;gap:10px;align-items:end;margin:18px 0">
        <div class="pc-audit-field"><label>Customer</label><select name="customer_id" onchange="this.form.submit()"><option value="">Tất cả Customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string)$customerId===(string)$customer->id)>{{ $customer->name }}</option>@endforeach</select></div>
        <div class="pc-audit-field"><label>Tìm kiếm</label><input name="search" value="{{ $search }}" placeholder="Audit Code / Chi nhánh"></div>
        <button class="pc-audit-btn" type="submit">Tìm</button>
        @if($customerId || $search)<a class="pc-audit-btn secondary" href="{{ route('admin.pc_audit.codes') }}" style="display:inline-flex;align-items:center;justify-content:center;text-decoration:none">Xóa lọc</a>@endif
    </form>
    <div style="overflow-x:auto"><table class="pc-audit-table"><thead><tr><th>Audit Code</th><th>Customer</th><th>Chi nhánh</th><th>Trạng thái</th><th style="width:90px"></th></tr></thead><tbody>
    @forelse($codes as $code)<tr><td><span class="pc-audit-code">{{ $code->code }}</span></td><td>{{ $code->branch?->customer?->name ?? '—' }}</td><td>{{ $code->branch?->name ?? '—' }}</td><td><span class="pc-audit-badge {{ $code->is_active ? '' : 'off' }}">{{ $code->is_active ? 'Đang dùng' : 'Đã khóa' }}</span></td><td><form method="POST" action="{{ route('admin.pc_audit.codes.toggle',$code) }}">@csrf @method('PATCH')<button class="pc-audit-btn secondary" type="submit" style="height:34px;padding:0 10px">{{ $code->is_active ? 'Khóa' : 'Mở' }}</button></form></td></tr>
    @empty<tr><td colspan="5" style="text-align:center;padding:28px;color:#64748b">Chưa có Audit Code.</td></tr>@endforelse
    </tbody></table></div><div style="margin-top:14px">{{ $codes->links() }}</div>
</div>

<script>
(function(){const customer=document.getElementById('customer_id'),branch=document.getElementById('branch_id');if(!customer||!branch)return;const allOptions=Array.from(branch.querySelectorAll('option[data-customer]'));function refreshBranches(){const customerId=customer.value,current=branch.value;branch.innerHTML='<option value="">-- Chọn Chi nhánh --</option>';allOptions.filter(option=>option.dataset.customer===customerId).forEach(option=>branch.appendChild(option.cloneNode(true)));if(Array.from(branch.options).some(option=>option.value===current))branch.value=current;}customer.addEventListener('change',refreshBranches);refreshBranches();})();
</script>
@endsection
