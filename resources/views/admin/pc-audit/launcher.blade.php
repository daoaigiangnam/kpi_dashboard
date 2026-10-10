@extends('layouts.admin')
@section('title', 'PC Audit Launcher')
@section('content')
<style>
.pc-launcher{max-width:1050px;margin:0 auto;color:#0f172a}
.pc-launcher *{box-sizing:border-box}
.pc-launcher-hero{background:linear-gradient(135deg,#0f766e,#155e75);color:#fff;border-radius:18px;padding:26px;margin-bottom:18px}
.pc-launcher-hero h1{font-size:27px;font-weight:800;margin:0 0 7px}
.pc-launcher-hero p{margin:0;color:#d1fae5;font-size:14px}
.pc-launcher-card{background:#fff;border:1px solid #dbe4ee;border-radius:15px;padding:22px;margin-bottom:16px;box-shadow:0 5px 18px rgba(15,23,42,.04)}
.pc-launcher-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px}
.pc-launcher-field label{display:block;font-size:13px;font-weight:750;margin:0 0 7px;color:#334155}
.pc-launcher-field input,.pc-launcher-field select{width:100%;height:43px;border:1px solid #cbd5e1;border-radius:9px;padding:0 12px;background:#fff;color:#0f172a;font-size:14px}
.pc-launcher-field input:focus,.pc-launcher-field select:focus{outline:none;border-color:#14b8a6;box-shadow:0 0 0 3px rgba(20,184,166,.12)}
.pc-launcher-help{display:block;margin-top:5px;font-size:11px;color:#64748b;line-height:1.45}
.pc-launcher-error{padding:11px 13px;background:#fff1f2;border:1px solid #fecdd3;border-radius:9px;color:#9f1239;margin-bottom:14px;font-size:13px}
.pc-launcher-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:20px}
.pc-launcher-submit{border:0;border-radius:10px;background:#0f766e;color:#fff;font-weight:800;padding:13px 18px;cursor:pointer}
.pc-launcher-submit:hover{background:#115e59}
.pc-launcher-note{background:#f0fdfa;border:1px solid #99f6e4;border-radius:11px;padding:14px;color:#115e59;font-size:13px;line-height:1.55}
@media(max-width:700px){.pc-launcher-grid{grid-template-columns:1fr}.pc-launcher-hero{padding:20px}.pc-launcher-card{padding:16px}}
</style>
<div class="pc-launcher">
    <div class="pc-launcher-hero">
        <h1>🖥️ PC Audit Launcher</h1>
        <p>Chọn khách hàng và chi nhánh. Hệ thống tự tạo gói PC_Audit.ps1 và PC_Audit.bat để tải về.</p>
    </div>

    @if($errors->any())
        <div class="pc-launcher-error">
            <strong>Chưa thể tạo bộ Audit:</strong>
            <ul style="margin:6px 0 0 18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.pc_audit.launcher.generate') }}">
        @csrf
        <div class="pc-launcher-card">
            <h2 style="font-size:17px;font-weight:800;margin:0 0 5px">Thông tin phân bổ</h2>
            <p style="font-size:13px;color:#64748b;margin:0 0 18px">Chọn khách hàng trước; danh sách chi nhánh sẽ tự động lọc theo khách hàng.</p>
            <div class="pc-launcher-grid">
                <div class="pc-launcher-field">
                    <label for="customer_id">Khách hàng *</label>
                    <select name="customer_id" id="customer_id" required>
                        <option value="">-- Chọn khách hàng --</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string)old('customer_id')===(string)$customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pc-launcher-field">
                    <label for="branch_id">Chi nhánh *</label>
                    <select name="branch_id" id="branch_id" required>
                        <option value="">-- Chọn khách hàng trước --</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" data-customer-id="{{ $branch->customer_id }}" @selected((string)old('branch_id')===(string)$branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="pc-launcher-card">
            <div class="pc-launcher-note">
                <strong>Gói tải xuống gồm 2 file:</strong> <code>PC_Audit.ps1</code> và <code>PC_Audit.bat</code> trong một file ZIP. Giải nén cả hai file vào cùng thư mục rồi chạy BAT trên máy cần kiểm kê. Website không thể tự chạy BAT trực tiếp trên máy người dùng do giới hạn bảo mật của trình duyệt.
                <br><br>
                <strong>Chọn đúng hệ điều hành:</strong> nút Windows 7 tạo gói có lớp tương thích WMI và kiểm tra các lệnh bảo mật không có trên PowerShell cũ. Nút Windows 10/11+ tạo gói riêng cho Windows hiện đại. Máy đích vẫn cần kết nối HTTPS/TLS 1.2 tới máy chủ Audit.
            </div>
            <div class="pc-launcher-actions">
                <button type="submit" name="target_os" value="windows7" class="pc-launcher-submit">⬇ Tải bộ Audit Windows 7</button>
                <button type="submit" name="target_os" value="windows10" class="pc-launcher-submit">⬇ Tải bộ Audit Windows 10/11+</button>
                <a href="{{ route('admin.pc_audit.index') }}" style="color:#475569;font-size:13px;font-weight:700;text-decoration:none">Quay lại danh sách Audit</a>
            </div>
        </div>
    </form>
</div>
<script>
(function(){
    const customer=document.getElementById('customer_id');
    const branch=document.getElementById('branch_id');
    if(!customer||!branch)return;
    const options=Array.from(branch.querySelectorAll('option[data-customer-id]'));
    function refresh(preserve){
        const selectedCustomer=customer.value;
        const old=preserve?branch.value:'';
        options.forEach(option=>{
            const visible=selectedCustomer!=='' && option.dataset.customerId===selectedCustomer;
            option.hidden=!visible;
            option.disabled=!visible;
        });
        const match=options.find(option=>option.value===old&&!option.disabled);
        branch.value=match?old:'';
        branch.options[0].textContent=selectedCustomer?'-- Chọn chi nhánh --':'-- Chọn khách hàng trước --';
    }
    customer.addEventListener('change',()=>refresh(false));
    refresh(true);
})();
</script>
@endsection
