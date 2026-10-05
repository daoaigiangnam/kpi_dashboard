@extends('layouts.admin')
@section('title','PC Audit - Cấu hình')
@section('content')
<style>
    .pc-settings-page{--pa-primary:#0f766e;--pa-primary-dark:#115e59;--pa-accent:#14b8a6;--pa-soft:#ecfdf5;--pa-border:#e2e8f0;--pa-text:#0f172a;--pa-muted:#64748b}
    .pc-settings-page *{box-sizing:border-box}
    .pc-settings-hero{background:linear-gradient(135deg,#0f766e 0%,#155e75 100%);color:#fff;border-radius:18px;padding:25px 27px;margin-bottom:18px;box-shadow:0 10px 30px rgba(15,118,110,.16)}
    .pc-settings-hero-inner{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}
    .pc-settings-title{margin:0;font-size:26px;font-weight:800;letter-spacing:-.4px}
    .pc-settings-subtitle{margin:7px 0 0;color:rgba(255,255,255,.82);font-size:14px;line-height:1.5}
    .pc-settings-actions{display:flex;gap:8px;flex-wrap:wrap}
    .pc-settings-actions a{display:inline-flex;align-items:center;gap:6px;padding:9px 13px;border:1px solid rgba(255,255,255,.24);border-radius:9px;color:#fff;text-decoration:none;background:rgba(255,255,255,.11);font-size:13px;font-weight:700;transition:.15s}
    .pc-settings-actions a:hover{background:rgba(255,255,255,.2);transform:translateY(-1px)}
    .pc-settings-card{background:#fff;border:1px solid var(--pa-border);border-radius:16px;padding:20px 22px;box-shadow:0 5px 18px rgba(15,23,42,.045)}
    .pc-settings-head{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;flex-wrap:wrap;margin-bottom:17px}
    .pc-settings-head h2{margin:0;color:var(--pa-text);font-size:17px;font-weight:800}
    .pc-settings-head p{margin:5px 0 0;color:var(--pa-muted);font-size:13px;line-height:1.5}
    .pc-settings-badge{display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border-radius:999px;background:var(--pa-soft);border:1px solid #a7f3d0;color:#047857;font-size:12px;font-weight:800}
    .pc-settings-badge .dot{width:7px;height:7px;border-radius:50%;background:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.12)}
    .pc-settings-api{padding:15px 16px;background:linear-gradient(180deg,#f8fafc,#f1f5f9);border:1px solid var(--pa-border);border-radius:12px;margin-bottom:20px}
    .pc-settings-api-label{font-size:11px;text-transform:uppercase;letter-spacing:.5px;font-weight:800;color:#64748b;margin-bottom:7px}
    .pc-settings-api-row{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
    .pc-settings-api code{display:block;flex:1;min-width:280px;padding:9px 11px;background:#fff;border:1px solid #dbe3ec;border-radius:8px;color:#0f766e;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;overflow:auto}
    .pc-settings-copy{border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:8px;padding:8px 11px;font-size:12px;font-weight:700;cursor:pointer}
    .pc-settings-copy:hover{background:#f8fafc}
    .pc-settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px}
    .pc-settings-field label{display:block;margin:0 0 7px;font-size:12px;font-weight:800;color:#334155}
    .pc-settings-field input,.pc-settings-field textarea{width:100%;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:var(--pa-text);outline:none;transition:.15s;font-size:13px}
    .pc-settings-field input{height:41px;padding:0 12px}
    .pc-settings-field textarea{padding:10px 12px;resize:vertical;min-height:92px;line-height:1.5}
    .pc-settings-field input:focus,.pc-settings-field textarea:focus{border-color:var(--pa-accent);box-shadow:0 0 0 3px rgba(20,184,166,.12)}
    .pc-settings-help{display:block;margin-top:5px;color:#94a3b8;font-size:11px;line-height:1.45}
    .pc-settings-switch{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:13px 14px;border:1px solid var(--pa-border);border-radius:11px;background:#f8fafc;margin-top:18px}
    .pc-settings-switch-main{display:flex;align-items:center;gap:10px}
    .pc-settings-switch input{width:18px;height:18px;accent-color:var(--pa-primary);cursor:pointer}
    .pc-settings-switch strong{display:block;color:#1e293b;font-size:13px}
    .pc-settings-switch span{display:block;color:#64748b;font-size:11px;margin-top:2px}
    .pc-settings-actions-bottom{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:18px;padding-top:17px;border-top:1px solid #eef2f7}
    .pc-settings-save{height:40px;border:0;border-radius:9px;padding:0 17px;background:var(--pa-primary);color:#fff;font-weight:800;cursor:pointer;box-shadow:0 4px 10px rgba(15,118,110,.14)}
    .pc-settings-save:hover{background:var(--pa-primary-dark);transform:translateY(-1px)}
    .pc-settings-note{color:var(--pa-muted);font-size:11px}
    .pc-settings-info{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
    .pc-settings-info-item{padding:14px;border:1px solid var(--pa-border);border-radius:11px;background:#fff}
    .pc-settings-info-item .icon{font-size:19px;margin-bottom:6px}
    .pc-settings-info-item strong{display:block;color:#1e293b;font-size:13px;margin-bottom:4px}
    .pc-settings-info-item span{display:block;color:#64748b;font-size:11px;line-height:1.5}
    .pc-settings-success{display:flex;align-items:center;gap:9px;padding:12px 14px;margin-bottom:18px;border:1px solid #a7f3d0;border-radius:10px;background:#ecfdf5;color:#065f46;font-size:13px;font-weight:650}
    @media(max-width:850px){.pc-settings-grid,.pc-settings-info{grid-template-columns:1fr}.pc-settings-card{padding:17px}.pc-settings-hero{padding:21px}}
</style>

<div class="pc-settings-page">
    <div class="pc-settings-hero">
        <div class="pc-settings-hero-inner">
            <div>
                <h1 class="pc-settings-title">PC Audit — Cấu hình</h1>
                <p class="pc-settings-subtitle">Quản lý kết nối API, phiên bản Audit Tool và trạng thái cho phép máy trạm thực hiện Audit.</p>
            </div>
            <div class="pc-settings-actions">
                @if(auth()->user()->hasPermission('pc_audit.view'))<a href="{{ route('admin.pc_audit.index') }}">📋 Kết quả Audit</a>@endif
                @if(auth()->user()->hasPermission('pc_audit.codes'))<a href="{{ route('admin.pc_audit.codes') }}">🔑 Audit Code</a>@endif
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="pc-settings-success">✓ {{ session('success') }}</div>
    @endif

    <div class="pc-settings-card">
        <div class="pc-settings-head">
            <div>
                <h2>⚙️ Cấu hình Audit Tool</h2>
                <p>Các thông số dưới đây được Audit Tool đọc từ Server trước khi bắt đầu thu thập dữ liệu.</p>
            </div>
            <div class="pc-settings-badge"><span class="dot"></span>{{ old('enabled',$setting->enabled) ? 'Tool đang được phép chạy' : 'Tool đang bị khóa' }}</div>
        </div>

        <div class="pc-settings-api">
            <div class="pc-settings-api-label">Endpoint cấu hình</div>
            <div class="pc-settings-api-row">
                <code id="pc-audit-api-url">{{ rtrim(config('app.url'), '/') }}/api/pc-audit/config</code>
                <button type="button" class="pc-settings-copy" id="copy-api-url">📋 Sao chép</button>
            </div>
            <div class="pc-settings-help">Audit Tool sử dụng endpoint này để lấy API Base URL, phiên bản Tool và trạng thái cho phép Audit.</div>
        </div>

        <form method="POST" action="{{ route('admin.pc_audit.settings.update') }}">
            @csrf
            @method('PUT')

            <div class="pc-settings-grid">
                <div class="pc-settings-field">
                    <label>API Base URL <span style="color:#ef4444">*</span></label>
                    <input name="api_base_url" value="{{ old('api_base_url',$setting->api_base_url) }}" required>
                    <span class="pc-settings-help">Ví dụ: https://kpi.review360.id.vn/api</span>
                </div>
                <div class="pc-settings-field">
                    <label>Tool Version <span style="color:#ef4444">*</span></label>
                    <input name="tool_version" value="{{ old('tool_version',$setting->tool_version) }}" required>
                    <span class="pc-settings-help">Phiên bản Audit Tool đang phát hành.</span>
                </div>
                <div class="pc-settings-field">
                    <label>Minimum Tool Version <span style="color:#ef4444">*</span></label>
                    <input name="minimum_tool_version" value="{{ old('minimum_tool_version',$setting->minimum_tool_version) }}" required>
                    <span class="pc-settings-help">Phiên bản thấp hơn sẽ không được phép thực hiện Audit.</span>
                </div>
                <div class="pc-settings-field">
                    <label>Download URL <span style="font-weight:500;color:#94a3b8">(tùy chọn)</span></label>
                    <input name="download_url" value="{{ old('download_url',$setting->download_url) }}" placeholder="https://.../AuditTool.exe">
                    <span class="pc-settings-help">Đường dẫn tải AuditTool.exe khi triển khai cơ chế cập nhật Tool.</span>
                </div>
            </div>

            <div class="pc-settings-switch">
                <div class="pc-settings-switch-main">
                    <input type="checkbox" id="pc-audit-enabled" name="enabled" value="1" @checked(old('enabled',$setting->enabled))>
                    <label for="pc-audit-enabled" style="margin:0;cursor:pointer">
                        <strong>Cho phép User chạy PC Audit</strong>
                        <span>Bật để các máy trạm có Audit Code hợp lệ được phép gửi dữ liệu về Server.</span>
                    </label>
                </div>
            </div>

            <div class="pc-settings-field" style="margin-top:17px">
                <label>Thông báo khi khóa Tool</label>
                <textarea name="disabled_message" rows="3" placeholder="Hệ thống Audit hiện đang tạm ngưng. Vui lòng thử lại sau.">{{ old('disabled_message',$setting->disabled_message) }}</textarea>
                <span class="pc-settings-help">Nội dung hiển thị cho User khi hệ thống không cho phép chạy Audit.</span>
            </div>

            <div class="pc-settings-actions-bottom">
                <button type="submit" class="pc-settings-save">💾 Lưu cấu hình</button>
                <span class="pc-settings-note">Cấu hình được áp dụng cho các lần Audit tiếp theo.</span>
            </div>
        </form>
    </div>

    <div class="pc-settings-card" style="margin-top:18px">
        <div class="pc-settings-head" style="margin-bottom:13px">
            <div>
                <h2>📌 Thông tin vận hành</h2>
                <p>Luồng dữ liệu của PC Audit được quản lý tập trung trên Server.</p>
            </div>
        </div>
        <div class="pc-settings-info">
            <div class="pc-settings-info-item"><div class="icon">🔐</div><strong>Audit Code</strong><span>Code xác định Customer và Chi nhánh trước khi máy bắt đầu Audit.</span></div>
            <div class="pc-settings-info-item"><div class="icon">💻</div><strong>Audit Tool</strong><span>Tool lấy cấu hình từ API rồi thu thập thông tin phần cứng, Windows, Network, Security và Software.</span></div>
            <div class="pc-settings-info-item"><div class="icon">☁️</div><strong>Server</strong><span>Dữ liệu Audit được gửi về Server để lưu trữ, chấm điểm và xuất báo cáo Excel.</span></div>
        </div>
    </div>

    <div class="pc-settings-card" style="margin-top:18px">
        <div class="pc-settings-head" style="margin-bottom:0">
            <div>
                <h2>✉️ Customer & Email</h2>
                <p>PC Audit không khai báo Customer hoặc Email riêng. Customer và người nhận Email được kế thừa từ module Service. Khi Audit gửi thông báo, Server tự lấy Email nhận đang hoạt động của Customer tương ứng.</p>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const btn=document.getElementById('copy-api-url');
    const code=document.getElementById('pc-audit-api-url');
    if(!btn||!code)return;
    btn.addEventListener('click',async function(){
        try{
            await navigator.clipboard.writeText(code.textContent.trim());
            const old=this.textContent;
            this.textContent='✓ Đã sao chép';
            setTimeout(()=>this.textContent=old,1500);
        }catch(e){
            const range=document.createRange();
            range.selectNodeContents(code);
            const sel=window.getSelection();
            sel.removeAllRanges();sel.addRange(range);
        }
    });
})();
</script>
@endsection
