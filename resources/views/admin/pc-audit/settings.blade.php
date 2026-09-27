@extends('layouts.admin')
@section('title','PC Audit - Cấu hình')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div>
            <h2>PC Audit — Cấu hình API</h2>
            <p class="muted">Chỉ cấu hình những thông số cần cho Audit Tool lấy cấu hình và gửi dữ liệu về Server.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="button" href="{{ route('admin.pc_audit.index') }}">Kết quả Audit</a>
            <a class="button" href="{{ route('admin.pc_audit.codes') }}">Audit Code</a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="card" style="margin-top:14px">{{ session('success') }}</div>
@endif

<div class="card" style="margin-top:18px">
    <h3 style="margin-top:0">API cấu hình cho Audit Tool</h3>
    <div style="padding:12px 14px;background:#f6f8fa;border-radius:6px;margin-bottom:18px">
        <div style="font-weight:600">Link API cấu hình</div>
        <code>{{ rtrim(config('app.url'), '/') }}/api/pc-audit/config</code>
        <div class="muted" style="margin-top:5px">Tool dùng URL này để lấy API Base URL, phiên bản Tool và trạng thái cho phép Audit.</div>
    </div>

    <form method="POST" action="{{ route('admin.pc_audit.settings.update') }}">
        @csrf
        @method('PUT')

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px">
            <div>
                <label>API Base URL</label>
                <input name="api_base_url" value="{{ old('api_base_url',$setting->api_base_url) }}" required>
                <small class="muted">Ví dụ: https://kpi.review360.id.vn/api</small>
            </div>
            <div>
                <label>Tool Version</label>
                <input name="tool_version" value="{{ old('tool_version',$setting->tool_version) }}" required>
            </div>
            <div>
                <label>Minimum Tool Version</label>
                <input name="minimum_tool_version" value="{{ old('minimum_tool_version',$setting->minimum_tool_version) }}" required>
            </div>
            <div>
                <label>Download URL <span class="muted">(nếu có)</span></label>
                <input name="download_url" value="{{ old('download_url',$setting->download_url) }}" placeholder="https://.../AuditTool.exe">
            </div>
        </div>

        <div style="margin-top:18px">
            <label><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$setting->enabled))> Cho phép User chạy PC Audit</label>
        </div>

        <div style="margin-top:16px">
            <label>Thông báo khi khóa Tool</label>
            <textarea name="disabled_message" rows="3" placeholder="Hệ thống Audit hiện đang tạm ngưng. Vui lòng thử lại sau.">{{ old('disabled_message',$setting->disabled_message) }}</textarea>
        </div>

        <div style="margin-top:16px">
            <button type="submit">Lưu cấu hình</button>
        </div>
    </form>
</div>

<div class="card" style="margin-top:18px">
    <h3 style="margin-top:0">Customer & Email</h3>
    <p class="muted" style="margin-bottom:0">
        PC Audit <strong>không khai báo Customer hoặc Email riêng</strong>. Customer và người nhận Email được kế thừa từ module <strong>Service</strong>.
        Khi Audit gửi thông báo, Server tự lấy Email nhận đang hoạt động của Customer tương ứng.
    </p>
</div>
@endsection
