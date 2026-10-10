@extends('layouts.admin')
@section('title','Chỉnh sửa PC Audit')
@section('content')
<style>
    .pc-edit-page{--p:#0f766e;--pd:#115e59;--border:#dbe5e7;--text:#0f172a;--muted:#64748b;background:#f5f9f8;min-height:calc(100vh - 80px);padding:8px 0 32px}
    .pc-edit-page *{box-sizing:border-box}
    .pc-edit-wrap{max-width:980px;margin:0 auto}
    .pc-edit-hero{background:linear-gradient(135deg,#0f766e,#155e75);color:#fff;border-radius:18px;padding:24px 28px;margin-bottom:18px;box-shadow:0 10px 28px rgba(15,118,110,.14)}
    .pc-edit-hero h1{margin:0;font-size:25px;font-weight:750}.pc-edit-hero p{margin:7px 0 0;color:rgba(255,255,255,.82);font-size:13px}
    .pc-edit-card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:24px;box-shadow:0 5px 18px rgba(15,23,42,.045);margin-bottom:16px}
    .pc-edit-card h2{margin:0 0 5px;font-size:17px;color:var(--text)}.pc-edit-card .hint{margin:0 0 20px;color:var(--muted);font-size:13px}
    .pc-edit-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.pc-edit-field.full{grid-column:1/-1}
    .pc-edit-field label{display:block;font-size:13px;font-weight:700;color:#334155;margin-bottom:7px}.pc-edit-field label span{color:#dc2626}
    .pc-edit-field input,.pc-edit-field select{width:100%;height:44px;border:1px solid #cbd5e1;border-radius:10px;padding:0 13px;background:#fff;color:var(--text);font-size:14px;outline:none}
    .pc-edit-field input:focus,.pc-edit-field select:focus{border-color:#14b8a6;box-shadow:0 0 0 3px rgba(20,184,166,.12)}
    .pc-edit-field small{display:block;color:var(--muted);font-size:11px;margin-top:6px}.pc-edit-error{color:#b91c1c;font-size:12px;margin-top:6px}
    .pc-edit-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:18px}.pc-edit-summary-item{background:#f8fafc;border:1px solid #e2e8f0;border-radius:11px;padding:12px}.pc-edit-summary-item b{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px}.pc-edit-summary-item span{font-size:13px;font-weight:700;color:#0f172a;word-break:break-word}
    .pc-edit-actions{display:flex;justify-content:space-between;gap:10px;align-items:center}.pc-edit-actions-right{display:flex;gap:9px}
    .pc-edit-btn{display:inline-flex;align-items:center;justify-content:center;height:42px;padding:0 17px;border-radius:10px;text-decoration:none;border:1px solid #cbd5e1;background:#fff;color:#334155;font-weight:700;font-size:13px;cursor:pointer}.pc-edit-btn.primary{background:var(--p);border-color:var(--p);color:#fff}.pc-edit-btn.primary:hover{background:var(--pd)}
    .pc-edit-alert{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:11px;padding:12px 14px;margin-bottom:16px;font-size:13px}.pc-edit-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:11px;padding:12px 14px;margin-bottom:16px;font-size:13px}
    @media(max-width:700px){.pc-edit-grid{grid-template-columns:1fr}.pc-edit-field.full{grid-column:auto}.pc-edit-summary{grid-template-columns:1fr 1fr}.pc-edit-actions{align-items:stretch;flex-direction:column}.pc-edit-actions-right{width:100%}.pc-edit-actions-right .pc-edit-btn{flex:1}}
</style>

<div class="pc-edit-page">
    <div class="pc-edit-wrap">
        <div class="pc-edit-hero">
            <h1>✏️ Chỉnh sửa thông tin PC Audit</h1>
            <p>Cập nhật Họ tên, Di động, Email, Customer và Chi nhánh. Thông tin phần cứng, phần mềm và kết quả Audit được giữ nguyên.</p>
        </div>

        @if($errors->any())
            <div class="pc-edit-alert">
                <strong>Không thể lưu thay đổi:</strong>
                <ul style="margin:7px 0 0 18px;padding:0">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.pc_audit.update', $pcAudit) }}">
            @csrf
            @method('PUT')

            <div class="pc-edit-card">
                <h2>Thông tin người sử dụng & phân bổ</h2>
                <p class="hint">Các trường dưới đây dùng để điều chỉnh thông tin quản lý của bản ghi Audit.</p>

                <div class="pc-edit-grid">
                    <div class="pc-edit-field full">
                        <label for="employee_name">Họ Tên</label>
                        <input id="employee_name" name="employee_name" value="{{ old('employee_name', $pcAudit->employee_name) }}" maxlength="150" placeholder="Nhập họ và tên người sử dụng máy">
                        @error('employee_name')<div class="pc-edit-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="pc-edit-field">
                        <label for="employee_mobile">Di động</label>
                        <input id="employee_mobile" name="employee_mobile" type="tel" value="{{ old('employee_mobile', $pcAudit->employee_mobile) }}" maxlength="50" autocomplete="tel" placeholder="Nhập số điện thoại">
                        @error('employee_mobile')<div class="pc-edit-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="pc-edit-field">
                        <label for="employee_email">Email</label>
                        <input id="employee_email" name="employee_email" type="email" value="{{ old('employee_email', $pcAudit->employee_email) }}" maxlength="190" autocomplete="email" placeholder="name@company.com">
                        @error('employee_email')<div class="pc-edit-error">{{ $message }}</div>@enderror
                    </div>

                    @php
                        $currentCustomerId = old('customer_id', $pcAudit->auditCode?->branch?->customer_id);
                        $currentBranchId = old('branch_id', $pcAudit->auditCode?->branch_id);
                    @endphp

                    <div class="pc-edit-field">
                        <label for="customer_id">Customer <span>*</span></label>
                        <select id="customer_id" name="customer_id" required>
                            <option value="">-- Chọn Customer --</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((string)$currentCustomerId === (string)$customer->id)>
                                    {{ $customer->name }}{{ $customer->code ? ' · '.$customer->code : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('customer_id')<div class="pc-edit-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="pc-edit-field">
                        <label for="branch_id">Chi nhánh <span>*</span></label>
                        <select id="branch_id" name="branch_id" required>
                            <option value="">-- Chọn Chi nhánh --</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" data-customer-id="{{ $branch->customer_id }}" @selected((string)$currentBranchId === (string)$branch->id)>
                                    {{ $branch->name }}{{ $branch->code ? ' · '.$branch->code : '' }}
                                </option>
                            @endforeach
                        </select>
                        <small>Chỉ hiển thị chi nhánh thuộc Customer đã chọn.</small>
                        @error('branch_id')<div class="pc-edit-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="pc-edit-summary">
                    <div class="pc-edit-summary-item"><b>Computer</b><span>{{ $pcAudit->computer_name ?: '—' }}</span></div>
                    <div class="pc-edit-summary-item"><b>Serial</b><span>{{ $pcAudit->serial_number ?: '—' }}</span></div>
                    <div class="pc-edit-summary-item"><b>Audit Code</b><span>{{ $pcAudit->auditCode?->code ?: '—' }}</span></div>
                    <div class="pc-edit-summary-item"><b>Điểm</b><span>{{ $pcAudit->audit_score !== null ? $pcAudit->audit_score.'%' : '—' }}</span></div>
                </div>
            </div>

            <div class="pc-edit-card">
                <div class="pc-edit-actions">
                    <a class="pc-edit-btn" href="{{ route('admin.pc_audit.index') }}">← Quay lại danh sách</a>
                    <div class="pc-edit-actions-right">
                        <a class="pc-edit-btn" href="{{ route('admin.pc_audit.show', $pcAudit) }}">Xem Audit</a>
                        <button class="pc-edit-btn primary" type="submit">💾 Lưu thay đổi</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
    const customer=document.getElementById('customer_id');
    const branch=document.getElementById('branch_id');
    if(!customer || !branch) return;
    const initialBranch=branch.value;
    function filterBranches(){
        const customerId=customer.value;
        let hasSelected=false;
        [...branch.options].forEach((option,index)=>{
            if(index===0){option.hidden=false;return;}
            const visible=option.dataset.customerId===customerId;
            option.hidden=!visible;
            if(visible && option.value===branch.value) hasSelected=true;
        });
        if(!hasSelected) branch.value='';
    }
    customer.addEventListener('change',filterBranches);
    filterBranches();
    if(initialBranch && branch.value===''){
        const option=[...branch.options].find(o=>o.value===initialBranch && !o.hidden);
        if(option) branch.value=initialBranch;
    }
})();
</script>
@endsection
