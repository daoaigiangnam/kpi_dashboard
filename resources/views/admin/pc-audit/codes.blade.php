@extends('layouts.admin')
@section('title','PC Audit - Audit Code')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div>
            <h2>PC Audit — Tạo Code</h2>
            <p class="muted">Customer lấy từ Service. Chỉ cần khai báo Chi nhánh và Phòng ban; hệ thống tự tạo Audit Code.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="button" href="{{ route('admin.pc_audit.index') }}">Kết quả Audit</a>
            <a class="button" href="{{ route('admin.pc_audit.settings') }}">Cấu hình API</a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="card" style="margin-top:14px">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="card" style="margin-top:14px">
        @foreach($errors->all() as $error)
            <div style="color:#b91c1c;margin-bottom:4px">{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="card" style="margin-top:18px">
    <h3 style="margin-top:0">1. Thêm Chi nhánh cho Customer</h3>
    <p class="muted">Chỉ khai báo khi Customer Service chưa có chi nhánh cần dùng cho PC Audit. Mã chi nhánh sẽ tự sinh.</p>
    <form method="POST" action="{{ route('admin.pc_audit.codes.store') }}">
        @csrf
        <input type="hidden" name="action" value="create_branch">
        <div style="display:grid;grid-template-columns:2fr 2fr auto;gap:12px;align-items:end">
            <div>
                <label>Customer</label>
                <select name="customer_id" required>
                    <option value="">-- Chọn Customer --</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id',$customerId)==$customer->id)>
                            {{ $customer->name }} ({{ $customer->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Chi nhánh</label>
                <input name="name" value="{{ old('name') }}" placeholder="Ví dụ: Hồ Chí Minh" required>
            </div>
            <button type="submit">+ Thêm Chi nhánh</button>
        </div>
    </form>
</div>

<div class="card" style="margin-top:18px">
    <h3 style="margin-top:0">2. Tạo Audit Code</h3>
    <p class="muted">User chỉ cần nhập Audit Code. Customer, Chi nhánh và Phòng ban sẽ được Server tự xác định.</p>
    <form method="POST" action="{{ route('admin.pc_audit.codes.store') }}" id="audit-code-form">
        @csrf
        <div style="display:grid;grid-template-columns:2fr 2fr 2fr auto;gap:12px;align-items:end">
            <div>
                <label>Customer</label>
                <select id="customer_id" required>
                    <option value="">-- Chọn Customer --</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string)old('customer_id',$customerId)===(string)$customer->id)>
                            {{ $customer->name }} ({{ $customer->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Chi nhánh</label>
                <select name="branch_id" id="branch_id" required>
                    <option value="">-- Chọn Customer trước --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" data-customer="{{ $branch->customer_id }}" @selected((string)old('branch_id')===(string)$branch->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Phòng ban</label>
                <input name="department" value="{{ old('department') }}" placeholder="Ví dụ: Kinh doanh" required>
            </div>
            <button type="submit">Tạo Code</button>
        </div>
    </form>
    <div style="margin-top:12px;padding:10px 12px;background:#f6f8fa;border-radius:6px" class="muted">
        Code sẽ tự tạo theo dạng: <strong>CUSTOMER-CHI-NHANH-PHONG-BAN</strong>. Nếu trùng, hệ thống tự thêm số thứ tự.
    </div>
</div>

<div class="card" style="margin-top:18px">
    <form method="GET" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        <div>
            <label>Customer</label>
            <select name="customer_id" onchange="this.form.submit()">
                <option value="">Tất cả</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string)$customerId===(string)$customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="flex:1;min-width:240px">
            <label>Tìm Audit Code</label>
            <input name="search" value="{{ $search }}" placeholder="Code / phòng ban">
        </div>
        <button type="submit">Lọc</button>
        @if($customerId || $search)
            <a class="button" href="{{ route('admin.pc_audit.codes') }}">Xóa lọc</a>
        @endif
    </form>
</div>

<div class="card" style="margin-top:18px;overflow:auto">
    <table style="width:100%;border-collapse:collapse;min-width:760px">
        <thead>
            <tr>
                <th>Audit Code</th>
                <th>Customer</th>
                <th>Chi nhánh</th>
                <th>Phòng ban</th>
                <th>Trạng thái</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($codes as $code)
            <tr>
                <td><strong>{{ $code->code }}</strong></td>
                <td>{{ $code->branch?->customer?->name }}</td>
                <td>{{ $code->branch?->name }}</td>
                <td>{{ $code->department }}</td>
                <td>{{ $code->is_active ? 'Đang dùng' : 'Đã khóa' }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.pc_audit.codes.toggle',$code) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit">{{ $code->is_active ? 'Khóa' : 'Kích hoạt' }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6">Chưa có Audit Code.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="margin-top:14px">{{ $codes->links() }}</div>
</div>

<script>
(function () {
    const customer = document.getElementById('customer_id');
    const branch = document.getElementById('branch_id');
    if (!customer || !branch) return;

    const allOptions = Array.from(branch.querySelectorAll('option[data-customer]'));

    function refreshBranches() {
        const customerId = customer.value;
        const current = branch.value;
        branch.innerHTML = '<option value="">-- Chọn Chi nhánh --</option>';

        allOptions
            .filter(option => option.dataset.customer === customerId)
            .forEach(option => branch.appendChild(option.cloneNode(true)));

        if (Array.from(branch.options).some(option => option.value === current)) {
            branch.value = current;
        }
    }

    customer.addEventListener('change', refreshBranches);
    refreshBranches();
})();
</script>
@endsection
