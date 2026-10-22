<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Tra cứu tài sản | Review360</title>
<style>
:root{--green:#123b2a;--teal:#0f766e;--ink:#19372b;--muted:#6b7d73;--line:#dce8e1;--bg:#f2f7f4}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--ink)}
.wrap{max-width:780px;margin:auto;padding:16px}.hero{background:linear-gradient(135deg,#123b2a,#0f766e);color:#fff;border-radius:18px;padding:24px;margin-bottom:14px}
.kicker{font-size:12px;text-transform:uppercase;letter-spacing:.08em;opacity:.8;font-weight:700}.hero h1{font-size:25px;line-height:1.25;margin:8px 0}.hero p{margin:0;opacity:.9;font-size:14px}
.card{background:#fff;border:1px solid var(--line);border-radius:15px;padding:18px;margin:14px 0;box-shadow:0 3px 12px #153b2a0a}
.card h2{font-size:17px;margin:0 0 15px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field{min-width:0}.label{font-size:11px;color:var(--muted);font-weight:800;text-transform:uppercase;letter-spacing:.04em}.value{font-size:15px;font-weight:650;margin-top:4px;overflow-wrap:anywhere}.value.large{font-size:19px}.pill{display:inline-block;background:#fff1c7;color:#805400;padding:5px 10px;border-radius:30px;font-size:12px;font-weight:800}.pill.pass{background:#dcfce7;color:#166534}.pill.fail{background:#fee2e2;color:#991b1b}
.person{display:flex;gap:13px;align-items:center}.avatar{width:48px;height:48px;display:grid;place-items:center;background:#dff4e9;color:#12634d;border-radius:14px;font-weight:800;font-size:20px;flex:none}.person .name{font-size:17px;font-weight:750}.person .sub{color:var(--muted);font-size:13px;margin-top:3px}
.summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.metric{padding:12px;background:#f6faf7;border:1px solid #e4eee8;border-radius:10px}.metric .label{font-size:10px}.metric .value{font-size:14px}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 14px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:700;background:var(--teal);color:#fff}.btn.secondary{background:#fff;color:var(--teal);border:1px solid #b8d6c7}
.note{font-size:12px;line-height:1.55;color:var(--muted);margin:15px 2px 5px}.empty{color:var(--muted);font-size:13px}
@media(max-width:480px){.wrap{padding:10px}.hero{padding:20px}.hero h1{font-size:23px}.card{padding:15px}.grid{grid-template-columns:1fr 1fr;gap:12px}.value{font-size:14px}.summary{grid-template-columns:1fr 1fr}}
</style></head>
<body><main class="wrap">
<header class="hero"><div class="kicker">Review360 · Tra cứu bằng QR Code</div><h1>{{ $asset->computer_name ?: 'Thông tin tài sản' }}</h1><p>Trang tra cứu chỉ đọc · Không cần đăng nhập</p></header>

<section class="card"><h2>Thông tin người sử dụng</h2>
<div class="person"><div class="avatar">{{ mb_strtoupper(mb_substr(trim((string)($asset->employee_name ?: 'U')),0,1)) }}</div><div><div class="name">{{ $asset->employee_name ?: 'Chưa cập nhật người sử dụng' }}</div><div class="sub">{{ $asset->department ?: 'Chưa cập nhật phòng ban' }}</div></div></div>
<div class="grid" style="margin-top:17px">
<div class="field"><div class="label">Chi nhánh</div><div class="value">{{ $asset->auditCode?->branch?->name ?: '—' }}</div></div>
<div class="field"><div class="label">Đơn vị quản lý</div><div class="value">{{ $asset->auditCode?->branch?->customer?->name ?: '—' }}</div></div>
</div></section>

<section class="card"><h2>Thông tin cơ bản của máy</h2><div class="summary">
<div class="metric"><div class="label">Mã tài sản</div><div class="value">{{ $asset->asset_tag ?: 'Chưa có mã' }}</div></div>
<div class="metric"><div class="label">Serial Number</div><div class="value">{{ $asset->serial_number ?: '—' }}</div></div>
<div class="metric"><div class="label">Hãng sản xuất</div><div class="value">{{ $asset->manufacturer ?: '—' }}</div></div>
<div class="metric"><div class="label">Model</div><div class="value">{{ $asset->model ?: '—' }}</div></div>
<div class="metric"><div class="label">Hệ điều hành</div><div class="value">{{ is_array($os) ? ($os['caption'] ?? $os['name'] ?? $os['product_name'] ?? 'Đã kiểm kê') : ($os ?: '—') }}</div></div>
<div class="metric"><div class="label">Lần kiểm kê gần nhất</div><div class="value">{{ $asset->collected_at?->format('d/m/Y H:i') ?: '—' }}</div></div>
</div>
<div class="grid" style="margin-top:15px"><div class="field"><div class="label">Trạng thái Audit</div><div class="value"><span class="pill {{ strtoupper((string)$asset->audit_status)==='PASS'?'pass':(strtoupper((string)$asset->audit_status)==='FAIL'?'fail':'') }}">{{ $asset->audit_status ?: 'REVIEW' }}</span></div></div><div class="field"><div class="label">Điểm Audit</div><div class="value large">{{ $asset->audit_score !== null ? $asset->audit_score : '—' }}</div></div></div>
</section>

@if($hardware)
<section class="card"><h2>Tóm tắt cấu hình phần cứng</h2>
@php
$pick = function ($data, array $keys): array {
    if (is_string($data)) { $decoded = json_decode($data, true); if (is_array($decoded)) $data = $decoded; }
    if (!is_array($data)) return [];
    $rows = [];
    $walk = function ($value, int $depth = 0) use (&$walk, &$rows, $keys): void {
        if (count($rows) >= 8 || $depth > 3 || !is_array($value)) return;
        foreach ($value as $key => $item) {
            if (count($rows) >= 8) break;
            if (is_string($key) && in_array(strtolower($key), $keys, true) && (is_scalar($item) || $item === null)) {
                if ($item !== null && $item !== '') $rows[ucfirst(str_replace('_',' ',(string)$key))] = (string)$item;
            } elseif (is_array($item)) { $walk($item, $depth + 1); }
        }
    };
    $walk($data);
    return $rows;
};
$cpuRows = $pick($cpu, ['name','model','manufacturer','cores','threads','max_clock','processorname']);
$memoryRows = $pick($memory, ['capacity','size','speed','manufacturer','name','formfactor']);
$storageRows = $pick($storage, ['model','name','size','capacity','media_type','interfacetype']);
@endphp
<div class="grid">
@foreach([['CPU',$cpuRows],['RAM',$memoryRows],['Ổ lưu trữ',$storageRows]] as [$title,$rows])
<div class="metric"><div class="label">{{ $title }}</div>
@if(count($rows))@foreach($rows as $label=>$value)<div class="value">{{ $label }}: {{ $value }}</div>@endforeach
@else<div class="value">Chưa có dữ liệu chi tiết</div>@endif
</div>
@endforeach
</div>
</section>
@endif

<section class="card"><h2>Xem chi tiết Audit</h2><p class="empty">Để bảo vệ dữ liệu nội bộ, cấu hình đầy đủ và các tiêu chí Audit chi tiết chỉ dành cho tài khoản được cấp quyền.</p>
<div class="actions"><a class="btn" href="{{ route('customer.assets.show', $asset->asset_lookup_token) }}">Đăng nhập / Xem chi tiết tài sản</a></div>
</section>
<p class="note">QR này chỉ cung cấp thông tin nhận diện tài sản và người sử dụng ở mức cơ bản. Người có đường dẫn QR có thể xem các thông tin trên. Không chia sẻ mã QR ra ngoài phạm vi cần thiết; quản trị viên nên thu hồi token nếu mã bị lộ.</p>
</main></body></html>