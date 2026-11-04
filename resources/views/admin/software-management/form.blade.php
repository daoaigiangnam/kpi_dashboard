@extends('layouts.admin')
@section('title','Tạo yêu cầu cài đặt phần mềm – FR-16-01')
@section('content')
<div class="card">
    <div class="muted" style="margin-bottom:16px">Điền đủ thông tin áp dụng. Ticket sẽ được chuyển HOD phê duyệt; chưa được cài đặt trước khi hoàn tất các phê duyệt bắt buộc.</div>
    <form method="post" action="{{ route('admin.software_management.store') }}" class="form">@csrf
        <div class="field"><label>Tên phần mềm *</label><input class="input" name="software_name" required value="{{ old('software_name') }}"></div>
        <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:12px">
            <div class="field"><label>Nhà cung cấp</label><input class="input" name="vendor" value="{{ old('vendor') }}"></div>
            <div class="field"><label>Phiên bản</label><input class="input" name="version" value="{{ old('version') }}"></div>
            <div class="field"><label>Phòng ban</label><input class="input" name="department" value="{{ old('department',auth()->user()->departmentRelation?->name ?? auth()->user()->department) }}"></div>
            <div class="field"><label>Thiết bị / Hostname *</label><input class="input" name="device_name" required value="{{ old('device_name') }}"></div>
            <div class="field"><label>Mã tài sản</label><input class="input" name="asset_code" value="{{ old('asset_code') }}"></div>
            <div class="field"><label>HOD / Người quản lý *</label><select class="input" name="hod_id" required><option value="">-- Chọn HOD --</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(old('hod_id')==$u->id)>{{ $u->name }} ({{ $u->email }})</option>@endforeach</select></div>
            <div class="field"><label>IT phụ trách (nếu đã xác định)</label><select class="input" name="assigned_it_id"><option value="">Chưa phân công</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(old('assigned_it_id')==$u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div class="field"><label>Thời điểm cần</label><input class="input" type="datetime-local" name="needed_at" value="{{ old('needed_at') }}"></div>
            <div class="field"><label>Thời hạn sử dụng đến</label><input class="input" type="date" name="usage_until" value="{{ old('usage_until') }}"></div>
            <div class="field"><label>Chi phí dự kiến (VND)</label><input class="input" type="number" min="0" step="1000" name="estimated_cost" value="{{ old('estimated_cost') }}"></div>
            <div class="field"><label>Nguồn ngân sách</label><input class="input" name="budget_source" value="{{ old('budget_source') }}"></div>
        </div>
        <div class="field"><label>Mục đích nghiệp vụ / Lý do cần *</label><textarea class="input" name="business_purpose" rows="3" required>{{ old('business_purpose') }}</textarea></div>
        <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:12px">
            <div class="field"><label>Loại yêu cầu *</label><select class="input" name="request_type" required><option value="install" @selected(old('request_type','install')==='install')>Cài mới</option><option value="upgrade" @selected(old('request_type')==='upgrade')>Nâng cấp</option><option value="remove" @selected(old('request_type')==='remove')>Gỡ bỏ</option></select></div>
            <div class="field"><label>Nhánh xử lý *</label><select class="input" name="branch_type" required><option value="borrow" @selected(old('branch_type','borrow')==='borrow')>Borrow – dùng quyền sẵn có</option><option value="buy" @selected(old('branch_type')==='buy')>Buy – mua mới/bổ sung</option></select></div>
            <div class="field"><label>Loại quyền *</label><select class="input" name="permission_type" required><option value="readonly" @selected(old('permission_type')==='readonly')>Read-only / View-only</option><option value="edit" @selected(old('permission_type','edit')==='edit')>Chỉnh sửa</option><option value="other" @selected(old('permission_type')==='other')>Khác</option></select></div>
        </div>
        <div class="field"><label>Dữ liệu / hệ thống / mạng phần mềm cần truy cập</label><textarea class="input" name="data_access" rows="2">{{ old('data_access') }}</textarea></div>
        <div class="actions"><button class="btn" type="submit">Tạo Ticket và gửi HOD</button><a class="btn gray" href="{{ route('admin.software_management.index') }}">Hủy</a></div>
    </form>
</div>
@endsection