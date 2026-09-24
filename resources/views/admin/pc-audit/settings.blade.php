@extends('layouts.admin')
@section('title','PC Audit - Cấu hình')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div><h2>PC Audit — Cấu hình Tool</h2><p class="muted">Cấu hình tập trung. Máy User chỉ lấy cấu hình từ API bootstrap.</p></div>
        <div style="display:flex;gap:8px"><a class="button" href="{{ route('admin.pc_audit.index') }}">Kết quả Audit</a><a class="button" href="{{ route('admin.pc_audit.codes') }}">Audit Code</a><a class="button" href="{{ route('admin.pc_audit.recipients') }}">Email Customer</a></div>
    </div>
</div>
@if(session('success'))<div class="card" style="margin-top:14px">{{ session('success') }}</div>@endif
<div class="card" style="margin-top:18px">
<form method="POST" action="{{ route('admin.pc_audit.settings.update') }}">
@csrf @method('PUT')
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px">
<div><label>API Base URL</label><input name="api_base_url" value="{{ old('api_base_url',$setting->api_base_url) }}" required><small class="muted">Ví dụ: https://kpi.review360.id.vn/api</small></div>
<div><label>Tool Version</label><input name="tool_version" value="{{ old('tool_version',$setting->tool_version) }}" required></div>
<div><label>Minimum Tool Version</label><input name="minimum_tool_version" value="{{ old('minimum_tool_version',$setting->minimum_tool_version) }}" required></div>
<div><label>Download URL</label><input name="download_url" value="{{ old('download_url',$setting->download_url) }}"><small class="muted">Link tải bản Tool mới.</small></div>
</div>
<div style="margin-top:16px"><label><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$setting->enabled))> Cho phép User chạy PC Audit</label></div>
<div style="margin-top:16px"><label>Thông báo khi khóa Tool</label><textarea name="disabled_message" rows="4">{{ old('disabled_message',$setting->disabled_message) }}</textarea></div>
<div style="margin-top:16px"><button type="submit">Lưu cấu hình</button></div>
</form>
</div>
<div class="card" style="margin-top:18px"><h3>Mail</h3><p>PC Audit <strong>không có SMTP riêng</strong>. Email gửi đi sử dụng cấu hình Mail/SMTP chung của KPI Dashboard. Email nhận được cấu hình theo từng Customer.</p><a class="button" href="{{ route('admin.settings.index') }}">Mở cấu hình Mail KPI</a></div>
@endsection
