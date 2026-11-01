<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Monitoring dịch vụ | Review360</title>
<style>
:root{--green:#123b2a;--teal:#0f766e;--ink:#19372b;--muted:#718178;--line:#dfe9e3;--bg:#f3f7f5}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink)}
.wrap{max-width:1440px;margin:auto;padding:22px}.hero{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:23px;border-radius:16px;background:linear-gradient(135deg,#123b2a,#0f766e);color:#fff;margin-bottom:16px}
.hero h1{margin:0 0 7px;font-size:25px}.hero p{margin:0;color:#d7eee5;font-size:13px}.nav{display:flex;gap:8px;flex-wrap:wrap}.btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:9px;background:#fff;color:#145344;text-decoration:none;font-size:13px;font-weight:700}
.grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:16px}.card{background:#fff;border:1px solid var(--line);border-radius:13px;padding:16px;box-shadow:0 3px 12px #153b2a08;margin-bottom:16px}
.metric .label{font-size:12px;color:var(--muted);font-weight:650}.metric .number{font-size:27px;font-weight:800;margin-top:5px}.metric.online .number{color:#15803d}.metric.offline .number{color:#dc2626}.metric.unknown .number{color:#b7791f}
.card h2{font-size:17px;margin:0 0 5px}.sub{font-size:12px;color:var(--muted);margin:0 0 14px}
.tablewrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{width:100%;min-width:950px;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:11px 12px;border-bottom:1px solid #e7eee9;vertical-align:top}th{background:#f5f9f7;color:#52665b;font-size:10px;text-transform:uppercase;white-space:nowrap}tbody tr:last-child td{border-bottom:0}
.muted{color:var(--muted);font-size:12px}.pill{display:inline-flex;padding:4px 9px;border-radius:30px;font-size:11px;font-weight:800;background:#f1f5f9;color:#475569}.pill.online,.pill.resolved{background:#dcfce7;color:#166534}.pill.offline,.pill.open{background:#fee2e2;color:#991b1b}.pill.pending,.pill.acknowledged,.pill.warning{background:#fef3c7;color:#92400e}
.two{display:grid;grid-template-columns:1fr 1fr;gap:16px}.empty{text-align:center;padding:26px;color:var(--muted);font-size:13px}.target{font-family:ui-monospace,monospace;font-size:12px;overflow-wrap:anywhere}
@media(max-width:900px){.grid{grid-template-columns:repeat(3,minmax(0,1fr))}.two{grid-template-columns:1fr}}
@media(max-width:560px){.wrap{padding:10px}.hero{padding:18px}.hero h1{font-size:21px}.grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.card{padding:12px}.metric .number{font-size:23px}}
</style>
</head>
<body><main class="wrap">
<header class="hero"><div><div style="font-size:11px;letter-spacing:.08em;font-weight:800;color:#bde8d7">REVIEW360 · CUSTOMER PORTAL</div><h1>Monitoring dịch vụ</h1><p>Theo dõi tình trạng kết nối và cảnh báo các dịch vụ của doanh nghiệp.</p></div><nav class="nav"><a class="btn" href="{{ route('customer.assets.index') }}">Quản lý tài sản</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn" type="submit">Đăng xuất</button></form></nav></header>

<section class="grid">
<div class="card metric"><div class="label">Tổng dịch vụ</div><div class="number">{{ number_format($stats['total']) }}</div></div>
<div class="card metric online"><div class="label">Đang Online</div><div class="number">{{ number_format($stats['online']) }}</div></div>
<div class="card metric offline"><div class="label">Đang Offline</div><div class="number">{{ number_format($stats['offline']) }}</div></div>
<div class="card metric unknown"><div class="label">Chưa xác định</div><div class="number">{{ number_format($stats['unknown']) }}</div></div>
<div class="card metric"><div class="label">Chưa cấu hình</div><div class="number">{{ number_format($stats['not_configured']) }}</div></div>
</section>

<section class="card"><h2>Danh sách dịch vụ</h2><p class="sub">Tình trạng monitoring gần nhất của các dịch vụ được đăng ký cho doanh nghiệp.</p>
<div class="tablewrap"><table><thead><tr><th>Dịch vụ</th><th>Loại dịch vụ</th><th>Nhà cung cấp</th><th>Đối tượng giám sát</th><th>Phương thức</th><th>Trạng thái</th><th>Latency</th><th>Packet Loss</th><th>Kiểm tra gần nhất</th><th>SSL</th></tr></thead><tbody>
@forelse($services as $service)
@php
$status = $service->monitor_status ?: 'pending';
$statusLabel = ['online'=>'Online','offline'=>'Offline','pending'=>'Chưa có dữ liệu','unknown'=>'Chưa xác định'][$status] ?? ucfirst($status);
$configured = in_array($service->monitor_check_method, ['ping','port'], true) && $service->monitor_target;
@endphp
<tr><td><strong>{{ $service->service_name }}</strong><div class="muted">{{ $service->value ?: '—' }}</div></td>
<td>{{ $service->serviceType?->name ?: '—' }}</td><td>{{ $service->provider?->name ?: '—' }}</td>
<td class="target">{{ $configured ? $service->monitor_target : 'Chưa cấu hình' }}@if($configured && $service->monitor_check_method==='port' && $service->monitor_port)<div class="muted">Port {{ $service->monitor_port }}</div>@endif</td>
<td>{{ $configured ? strtoupper($service->monitor_check_method) : '—' }}</td>
<td>@if(!$configured)<span class="pill">Chưa cấu hình</span>@else<span class="pill {{ $status }}">{{ $statusLabel }}</span>@endif</td>
<td>{{ $service->monitor_last_latency_ms !== null ? $service->monitor_last_latency_ms.' ms' : '—' }}</td>
<td>{{ $service->monitor_packet_loss_percent !== null ? $service->monitor_packet_loss_percent.'%' : '—' }}</td>
<td>{{ $service->monitor_last_checked_at?->format('d/m/Y H:i:s') ?: 'Chưa kiểm tra' }}</td>
<td>@if($service->ssl_detected)<span class="pill {{ $service->ssl_status==='valid'?'online':($service->ssl_status==='expired'?'offline':'pending') }}">{{ $service->ssl_status ?: 'Đang kiểm tra' }}</span><div class="muted">Hết hạn: {{ $service->ssl_expiry_date?->format('d/m/Y') ?: '—' }}</div>@else<span class="muted">Không áp dụng</span>@endif</td></tr>
@empty<tr><td colspan="10" class="empty">Chưa có dịch vụ được khai báo cho doanh nghiệp.</td></tr>@endforelse
</tbody></table></div>
</section>

<div class="two">
<section class="card"><h2>Sự cố Monitoring</h2><p class="sub">Lịch sử các lần phát hiện và khôi phục trạng thái kết nối.</p><div class="tablewrap"><table style="min-width:560px"><thead><tr><th>Dịch vụ</th><th>Trạng thái</th><th>Bắt đầu</th><th>Khôi phục</th><th>Thời lượng</th></tr></thead><tbody>
@forelse($networkIncidents as $event)<tr><td>{{ $event->service?->service_name ?: '—' }}<div class="muted">{{ $event->check_method ? strtoupper($event->check_method) : '' }}</div></td><td><span class="pill {{ $event->status==='open'?'offline':'resolved' }}">{{ $event->status==='open'?'Đang xảy ra':'Đã khôi phục' }}</span></td><td>{{ $event->started_at?->format('d/m/Y H:i') ?: '—' }}</td><td>{{ $event->resolved_at?->format('d/m/Y H:i') ?: '—' }}</td><td>{{ $event->duration_seconds !== null ? gmdate('H:i:s',(int)$event->duration_seconds) : '—' }}</td></tr>
@empty<tr><td colspan="5" class="empty">Chưa ghi nhận sự cố Monitoring.</td></tr>@endforelse
</tbody></table></div></section>

<section class="card"><h2>Cảnh báo dịch vụ</h2><p class="sub">Các cảnh báo hết hạn dịch vụ và chứng chỉ đang cần theo dõi.</p><div class="tablewrap"><table style="min-width:480px"><thead><tr><th>Dịch vụ</th><th>Loại cảnh báo</th><th>Mức</th><th>Ngày cảnh báo</th><th>Trạng thái</th></tr></thead><tbody>
@forelse($serviceAlerts as $alert)<tr><td>{{ $alert->service?->service_name ?: '—' }}</td><td>{{ $alert->alert_type==='ssl_expiry'?'SSL':($alert->alert_type==='service_expiry'?'Hết hạn dịch vụ':$alert->alert_type) }}</td><td>{{ $alert->alert_stage ? 'Mức '.$alert->alert_stage : '—' }}</td><td>{{ $alert->triggered_at?->format('d/m/Y H:i') ?: '—' }}</td><td><span class="pill {{ $alert->status }}">{{ $alert->status==='acknowledged'?'Đã tiếp nhận':'Đang mở' }}</span></td></tr>
@empty<tr><td colspan="5" class="empty">Không có cảnh báo đang mở.</td></tr>@endforelse
</tbody></table></div></section>
</div>
<p class="muted" style="text-align:center">Dữ liệu được cập nhật theo chu kỳ monitoring do bộ phận CNTT cấu hình. Trang này chỉ cung cấp quyền xem, không thực hiện kiểm tra chủ động.</p>
</main></body></html>