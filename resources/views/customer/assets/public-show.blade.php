<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Tra cứu tài sản</title>
<style>body{font-family:Arial,sans-serif;margin:0;background:#f3f7f5;color:#163328}.wrap{max-width:720px;margin:auto;padding:16px}.hero{background:#123b2a;color:#fff;padding:22px;border-radius:14px}.card{background:#fff;border:1px solid #dfe9e3;border-radius:12px;padding:18px;margin-top:14px}.label{color:#64776c;font-size:12px;text-transform:uppercase;font-weight:700}.value{font-size:16px;font-weight:600;overflow-wrap:anywhere;margin:4px 0 15px}.readonly{font-size:12px;color:#64776c}</style></head>
<body><main class="wrap"><header class="hero"><h1 style="margin-top:0">Thông tin tài sản</h1><div>Tra cứu bằng QR Code · Chỉ xem</div></header>
<section class="card">
<div class="label">Tên thiết bị</div><div class="value">{{ $asset->computer_name ?: 'Chưa cập nhật' }}</div>
<div class="label">Mã tài sản</div><div class="value">{{ $asset->asset_tag ?: 'Chưa cập nhật' }}</div>
<div class="label">Nhà sản xuất / Model</div><div class="value">{{ trim(($asset->manufacturer ?: '').' '.($asset->model ?: '')) ?: 'Chưa cập nhật' }}</div>
<div class="label">Trạng thái kiểm kê</div><div class="value">{{ $asset->audit_status ?: 'Chưa xác định' }}</div>
<div class="label">Đơn vị quản lý</div><div class="value">{{ $asset->auditCode?->branch?->customer?->name ?: '—' }}</div>
<div class="label">Chi nhánh</div><div class="value">{{ $asset->auditCode?->branch?->name ?: '—' }}</div>
<div class="label">Lần kiểm kê gần nhất</div><div class="value">{{ $asset->collected_at?->format('d/m/Y H:i') ?: '—' }}</div>
<p class="readonly">Trang tra cứu công khai chỉ đọc. Không hiển thị dữ liệu kiểm toán chi tiết, cấu hình hệ thống, địa chỉ IP hoặc thông tin đăng nhập.</p>
</section></main></body></html>