<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Chi tiết tài sản | Review360</title>
<style>
:root{--green:#123b2a;--teal:#0f766e;--ink:#19372b;--muted:#6b7d73;--line:#dce8e1;--bg:#f2f7f4}*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--ink)}.wrap{max-width:1000px;margin:auto;padding:14px}.hero{background:linear-gradient(135deg,#123b2a,#0f766e);color:#fff;border-radius:16px;padding:22px;margin-bottom:14px}.hero h1{font-size:24px;line-height:1.25;margin:8px 0}.muted{font-size:12px;color:var(--muted)}.hero .muted{color:#d9e9e1}.card{background:#fff;border:1px solid var(--line);border-radius:13px;padding:17px;margin:13px 0;box-shadow:0 3px 12px #153b2a0a}.card h2{font-size:17px;margin:0 0 13px}.audit-section summary{cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:12px}.audit-section summary::-webkit-details-marker{display:none}.audit-section summary:after{content:'＋';font-size:18px;color:var(--teal)}.audit-section[open] summary:after{content:'−'}.audit-section summary h2{margin:0}.audit-section .section-body{margin-top:14px}.audit-section{border-top:1px solid var(--line);padding:12px 0}.audit-section>summary{font-weight:750;color:var(--ink);cursor:pointer;display:flex;justify-content:space-between;gap:10px;list-style:none}.audit-section>summary:after{content:'＋';color:var(--teal)}.audit-section[open]>summary:after{content:'−'}.detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:12px}.detail-item{min-width:0;padding:10px;border:1px solid #e5eee8;border-radius:8px;background:#f8fbf9}.detail-line{overflow-wrap:anywhere;white-space:pre-wrap;font-size:12px;line-height:1.5}.detail-line span{font-weight:700;color:var(--muted)}.record-card{border:1px solid var(--line);border-radius:9px;padding:11px;margin:10px 0}.record-title{font-size:12px;font-weight:800;color:var(--teal);margin-bottom:8px}@media(max-width:520px){.detail-grid{grid-template-columns:1fr}}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.field{min-width:0;padding:10px;background:#f7faf8;border:1px solid #e5eee8;border-radius:9px}.label{font-size:10px;text-transform:uppercase;letter-spacing:.04em;font-weight:800;color:var(--muted)}.value{font-size:14px;font-weight:650;margin-top:4px;overflow-wrap:anywhere;white-space:pre-wrap}.person{display:flex;gap:12px;align-items:center}.avatar{width:46px;height:46px;border-radius:12px;background:#dff4e9;display:grid;place-items:center;color:#12634d;font-size:20px;font-weight:800;flex:none}.person-name{font-size:17px;font-weight:750}.pill{display:inline-block;padding:5px 10px;border-radius:30px;background:#fef3c7;color:#92400e;font-size:11px;font-weight:800}.pill.pass{background:#dcfce7;color:#166534}.pill.fail{background:#fee2e2;color:#991b1b}.tablewrap{overflow:auto;border:1px solid var(--line);border-radius:9px}table{width:100%;border-collapse:collapse;font-size:12px;min-width:500px}th,td{text-align:left;padding:9px;border-bottom:1px solid #e7eee9;vertical-align:top;overflow-wrap:anywhere}th{background:#f5f9f7;color:#52665b;font-size:10px;text-transform:uppercase;white-space:nowrap}tr:last-child td{border-bottom:0}.actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:15px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 14px;border-radius:9px;background:var(--teal);color:white;text-decoration:none;font-weight:700;font-size:13px}.note{font-size:12px;line-height:1.55;color:var(--muted);margin:14px 2px}
@media(max-width:680px){.wrap{padding:9px}.hero{padding:18px}.hero h1{font-size:21px}.card{padding:12px}.grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.field{padding:9px}.value{font-size:13px}.person-name{font-size:15px}.card h2{font-size:16px}}@media(max-width:370px){.grid{grid-template-columns:1fr}}
</style></head><body><main class="wrap">
@php
$systemFields = [
 ['Thông tin hệ thống', ['Mainboard'=>$asset->mainboard,'BIOS'=>$asset->bios,'Operating System'=>$asset->operating_system,'Windows Update'=>$asset->windows_update,'Last Boot'=>$asset->last_boot,'Uptime'=>$asset->uptime,'TPM'=>$asset->tpm,'Secure Boot'=>$asset->secure_boot]],
];
$collections = [
 ['RAM', $asset->memory], ['Storage', $asset->storage], ['Monitors', $asset->monitors], ['GPU', $asset->gpu],
 ['Battery', $asset->batteries], ['Network', $asset->network], ['Antivirus', $asset->antivirus],
 ['BitLocker', $asset->bitlocker], ['Firewall', $asset->firewall], ['Licenses', $asset->licenses], ['Software', $asset->software],
];
$flatten = function($value, string $prefix = '', int $depth = 0) use (&$flatten): array {
    if (is_string($value)) { $decoded = json_decode($value, true); if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) $value = $decoded; }
    if (!is_array($value)) return $value === null || $value === '' ? [] : [$prefix ?: 'Giá trị' => is_bool($value) ? ($value ? 'Có' : 'Không') : (string)$value];
    $rows = [];
    foreach ($value as $key=>$item) {
        if (in_array((string)$key, ['raw_payload','password','token','secret','credential'], true)) continue;
        $label = $prefix === '' ? (string)$key : $prefix.' / '.(string)$key;
        if (is_array($item)) {
            if ($depth < 4) $rows += $flatten($item, $label, $depth + 1);
            else $rows[$label] = '[Dữ liệu lồng nhau]';
        } elseif (is_object($item)) {
            $rows[$label] = '[Dữ liệu]';
        } elseif ($item !== null && $item !== '') {
            $rows[$label] = is_bool($item) ? ($item ? 'Có' : 'Không') : (string)$item;
        }
    }
    return $rows;
};
@endphp
<header class="hero"><div class="muted">REVIEW360 · TRA CỨU BẰNG QR CODE</div><h1>{{ $asset->computer_name ?: 'Thông tin tài sản' }}</h1><div class="muted">Chi tiết tài sản · Theo quyền tài khoản khách hàng</div></header>

<section class="card"><div class="actions" style="margin-top:0;margin-bottom:14px"><a class="btn" href="{{ route('customer.assets.index') }}">← Danh sách tài sản</a>@if(auth()->user()->hasPermission('customer.assets.qr'))<a class="btn" href="{{ route('customer.assets.qr',$asset->asset_lookup_token) }}">Mã QR</a>@endif @if(auth()->user()->hasPermission('customer.assets.edit'))<a class="btn" href="{{ route('customer.assets.edit',$asset->asset_lookup_token) }}">Sửa tài sản</a>@endif</div><h2>Thông tin người sử dụng</h2><div class="person"><div class="avatar">{{ mb_strtoupper(mb_substr(trim((string)($asset->employee_name ?: 'U')),0,1)) }}</div><div><div class="person-name">{{ $asset->employee_name ?: 'Chưa cập nhật người sử dụng' }}</div><div class="muted">{{ $asset->department ?: 'Chưa cập nhật phòng ban' }}</div></div></div>
<div class="grid" style="margin-top:13px">
@foreach(['Username'=>$asset->employee_username,'Email'=>$asset->employee_email,'Điện thoại'=>$asset->employee_mobile,'Domain'=>$asset->domain,'Đơn vị'=>$asset->auditCode?->branch?->customer?->name,'Chi nhánh'=>$asset->auditCode?->branch?->name] as $label=>$value)<div class="field"><div class="label">{{ $label }}</div><div class="value">{{ $value ?: '—' }}</div></div>@endforeach
</div></section>

<section class="card"><h2>Thông tin cơ bản của máy</h2><div class="grid">
@foreach(['Computer Name'=>$asset->computer_name,'Asset Tag'=>$asset->asset_tag,'Serial Number'=>$asset->serial_number,'Manufacturer'=>$asset->manufacturer,'Model'=>$asset->model,'Audit Code'=>$asset->auditCode?->code,'Ngày kiểm kê'=>$asset->collected_at?->format('d/m/Y H:i:s'),'Trạng thái Audit'=>$asset->audit_status,'Điểm Audit'=>$asset->audit_score] as $label=>$value)<div class="field"><div class="label">{{ $label }}</div><div class="value">@if($label==='Trạng thái Audit')<span class="pill {{ strtoupper((string)$value)==='PASS'?'pass':(strtoupper((string)$value)==='FAIL'?'fail':'') }}">{{ $value ?: 'REVIEW' }}</span>@else{{ $value ?? '—' }}@endif</div></div>@endforeach
</div></section>

<section class="card"><h2>Kết quả Audit</h2>
@php $auditResult=$asset->audit_results ?? []; $checks=$auditResult['results'] ?? []; $summary=$auditResult['summary'] ?? []; @endphp
<div class="grid" style="margin-bottom:12px">
@foreach(['PASS'=>$summary['pass'] ?? null,'REVIEW'=>$summary['review'] ?? null,'FAIL'=>$summary['fail'] ?? null] as $status=>$count)<div class="field"><div class="label">{{ $status }}</div><div class="value">{{ $count ?? '—' }}</div></div>@endforeach
</div>
@if(count($checks))<div class="tablewrap"><table><thead><tr><th>Tiêu chí</th><th>Kết quả</th><th>Ghi chú</th></tr></thead><tbody>@foreach($checks as $check)@if(is_array($check))<tr><td>{{ $check['label'] ?? $check['key'] ?? '—' }}</td><td><span class="pill {{ strtoupper((string)($check['status'] ?? ''))==='PASS'?'pass':(strtoupper((string)($check['status'] ?? ''))==='FAIL'?'fail':'') }}">{{ $check['status'] ?? 'REVIEW' }}</span></td><td>{{ $check['message'] ?? '' }}</td></tr>@endif @endforeach</tbody></table></div>@else<p class="muted">Không có tiêu chí Audit chi tiết.</p>@endif
</section>

<section class="card"><h2>Thông tin hệ thống</h2>
@foreach($systemFields as [$title,$fields])
<details class="audit-section"><summary>{{ $title }}</summary><div class="detail-grid">
@foreach($fields as $label=>$value)
@php $flat=$flatten($value, '', 0); @endphp
<div class="detail-item"><div class="label">{{ $label }}</div><div class="value">
@if(count($flat))@foreach($flat as $k=>$v)<div class="detail-line">@if($k!=='Giá trị')<span>{{ str_replace(['_',' / '],[' ',' / '],$k) }}:</span> @endif{{ $v }}</div>@endforeach @else—
@endif</div></div>
@endforeach
</div></details>
@endforeach
</section>
<section class="card"><h2>Chi tiết cấu hình thiết bị</h2><p class="muted">Đầy đủ dữ liệu thu thập được, chia theo từng nhóm. Mở nhóm cần xem để tránh trang quá dài trên điện thoại.</p>
@foreach($collections as [$title,$items])
<details class="audit-section"><summary>{{ $title }} <span class="muted">({{ $items->count() }})</span></summary>
@if($items->isEmpty())<p class="muted">Chưa có dữ liệu.</p>@else
@foreach($items as $item)
<div class="record-card"><div class="record-title">{{ $title }} @if($items->count()>1) · {{ $loop->iteration }}@endif</div><div class="detail-grid">
@foreach($item->getAttributes() as $field=>$value)
@if(!in_array($field,['id','pc_audit_id','created_at','updated_at']))
@php $flat=$flatten($value, '', 0); @endphp
<div class="detail-item"><div class="label">{{ str_replace('_',' ',$field) }}</div><div class="value">@if(count($flat))@foreach($flat as $k=>$v)<div class="detail-line">@if($k!=='Giá trị')<span>{{ $k }}:</span> @endif{{ $v }}</div>@endforeach @else—@endif</div></div>
@endif
@endforeach
</div></div>
@endforeach
@endif
</details>
@endforeach
</section>


<p class="note">Chi tiết tài sản chỉ dành cho tài khoản đã đăng nhập và được cấp quyền. Các trường chưa được thiết bị thu thập sẽ hiển thị dấu gạch ngang.</p>
</main></body></html>