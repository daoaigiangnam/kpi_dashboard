<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Chi tiết tài sản</title>
<style>
:root{--green:#123b2a;--teal:#0f766e;--line:#dce8e1;--muted:#718178;--ink:#19372b}*{box-sizing:border-box}body{margin:0;background:#f2f7f4;color:var(--ink);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{max-width:1150px;margin:auto;padding:22px}.hero{background:linear-gradient(135deg,#123b2a,#0f766e);color:white;border-radius:16px;padding:24px}.hero h1{margin:0 0 8px;font-size:25px;overflow-wrap:anywhere}.muted{color:var(--muted);font-size:13px}.hero .muted{color:#d9e9e1}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:9px;background:var(--teal);color:white;text-decoration:none;border:0;font-weight:650;font-size:13px}.btn.light{background:#fff;color:var(--teal)}.card{background:white;border:1px solid var(--line);border-radius:14px;padding:20px;margin-top:16px;box-shadow:0 3px 12px #153b2a08}.card h2{font-size:18px;margin:0 0 16px}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.field{padding:12px;background:#f7faf8;border:1px solid #e5eee8;border-radius:10px;min-width:0}.label{font-size:11px;color:var(--muted);font-weight:800;text-transform:uppercase;letter-spacing:.04em}.value{font-size:14px;font-weight:650;margin-top:5px;overflow-wrap:anywhere}.pill{display:inline-block;padding:4px 9px;border-radius:30px;background:#fef3c7;color:#92400e;font-size:11px;font-weight:800}.pill.pass{background:#dcfce7;color:#166534}.pill.fail{background:#fee2e2;color:#991b1b}.tablewrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}td,th{text-align:left;padding:11px;border-bottom:1px solid #e5eee8;vertical-align:top}th{background:#f5f9f7;color:#52665b;font-size:11px;text-transform:uppercase}.empty{padding:18px;background:#f7faf8;border-radius:10px;color:var(--muted);font-size:13px}.kv{display:flex;justify-content:space-between;gap:14px;padding:8px 0;border-bottom:1px solid #edf2ee;font-size:13px}.kv:last-child{border-bottom:0}.kv span:first-child{color:#607368}.kv span:last-child{text-align:right;font-weight:600;overflow-wrap:anywhere}@media(max-width:760px){.wrap{padding:10px}.hero{padding:19px}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.card{padding:14px}}@media(max-width:420px){.grid{grid-template-columns:1fr}.hero h1{font-size:21px}}
</style></head><body><main class="wrap">
<header class="hero"><div class="muted">CỔNG TÀI SẢN KHÁCH HÀNG</div><h1>{{ $asset->computer_name ?: 'Chi tiết tài sản' }}</h1><div class="muted">{{ $asset->auditCode?->branch?->customer?->name ?: '—' }} · {{ $asset->auditCode?->branch?->name ?: '—' }}</div>
<div class="actions"><a class="btn light" href="{{ route('customer.assets.index') }}">← Danh sách tài sản</a>@if(auth()->user()->hasPermission('customer.assets.qr'))<a class="btn light" href="{{ route('customer.assets.qr',$asset->asset_lookup_token) }}">Mã QR</a>@endif @if(auth()->user()->hasPermission('customer.assets.edit'))<a class="btn" href="{{ route('customer.assets.edit',$asset->asset_lookup_token) }}">Sửa tài sản</a>@endif</div></header>

<section class="card"><h2>Thông tin người sử dụng</h2><div class="grid">
@foreach(['Người sử dụng'=>$asset->employee_name,'Tên đăng nhập'=>$asset->employee_username,'Phòng ban'=>$asset->department,'Email người sử dụng'=>$asset->employee_email,'Điện thoại'=>$asset->employee_mobile,'Tên miền'=>$asset->domain] as $label=>$value)
<div class="field"><div class="label">{{ $label }}</div><div class="value">{{ $value ?: '—' }}</div></div>
@endforeach
</div></section>

<section class="card"><h2>Thông tin cơ bản của máy</h2><div class="grid">
@foreach(['Computer Name'=>$asset->computer_name,'Asset Tag'=>$asset->asset_tag,'Serial Number'=>$asset->serial_number,'Nhà sản xuất'=>$asset->manufacturer,'Model'=>$asset->model,'Audit Code'=>$asset->auditCode?->code,'Chi nhánh'=>$asset->auditCode?->branch?->name,'Ngày kiểm kê'=>$asset->collected_at?->format('d/m/Y H:i:s'),'Trạng thái Audit'=>$asset->audit_status,'Điểm Audit'=>$asset->audit_score] as $label=>$value)
<div class="field"><div class="label">{{ $label }}</div><div class="value">@if($label==='Trạng thái Audit')<span class="pill {{ strtoupper((string)$value)==='PASS'?'pass':(strtoupper((string)$value)==='FAIL'?'fail':'') }}">{{ $value ?: 'REVIEW' }}</span>@else{{ $value ?? '—' }}@endif</div></div>
@endforeach
</div></section>

@if(auth()->user()->hasPermission('customer.audit.view'))
<section class="card"><h2>Kết quả Audit</h2>
@if(is_array($asset->audit_results) && count($asset->audit_results))
<div class="tablewrap"><table><thead><tr><th>Tiêu chí</th><th>Trạng thái</th><th>Ghi chú</th></tr></thead><tbody>
@foreach(($asset->audit_results['results'] ?? $asset->audit_results) as $check)
@if(is_array($check))<tr><td>{{ $check['label'] ?? $check['name'] ?? $check['key'] ?? '—' }}</td><td><span class="pill {{ strtoupper((string)($check['status'] ?? ''))==='PASS'?'pass':(strtoupper((string)($check['status'] ?? ''))==='FAIL'?'fail':'') }}">{{ $check['status'] ?? 'REVIEW' }}</span></td><td>{{ $check['message'] ?? $check['description'] ?? '—' }}</td></tr>@endif
@endforeach
</tbody></table></div>
@else<div class="empty">Không có kết quả Audit chi tiết được lưu cho bản ghi này.</div>@endif
</section>

@php
$pick = function ($data, array $keys): array {
    if (is_string($data)) { $decoded = json_decode($data, true); if (is_array($decoded)) $data = $decoded; }
    if (!is_array($data)) return [];
    $rows = [];
    $walk = function ($value, int $depth = 0) use (&$walk, &$rows, $keys): void {
        if (count($rows) >= 10 || $depth > 3 || !is_array($value)) return;
        foreach ($value as $key => $item) {
            if (count($rows) >= 10) break;
            if (is_string($key) && in_array(strtolower($key), $keys, true) && (is_scalar($item) || $item === null)) {
                if ($item !== null && $item !== '') $rows[ucfirst(str_replace('_',' ',(string)$key))] = (string)$item;
            } elseif (is_array($item)) { $walk($item, $depth + 1); }
        }
    };
    $walk($data);
    return $rows;
};
$detail = $asset->details;
$sections = [
 ['CPU', $pick($detail?->cpu, ['name','model','manufacturer','cores','threads','max_clock','processorname'])],
 ['RAM', $pick($detail?->memory, ['capacity','size','speed','manufacturer','name','formfactor'])],
 ['Ổ lưu trữ', $pick($detail?->storage, ['model','name','size','capacity','media_type','interfacetype'])],
 ['Màn hình', $pick($detail?->monitors, ['name','manufacturer','model','size','resolution'])],
 ['GPU', $pick($detail?->gpu, ['name','manufacturer','model','memorysize'])],
 ['Pin', $pick($detail?->battery, ['name','manufacturer','designcapacity','fullchargecapacity','health'])],
 ['Hệ điều hành', $pick($detail?->windows, ['caption','name','version','buildnumber','architecture','productname'])],
 ['Mạng', $pick($detail?->network, ['name','description','manufacturer','speed','adaptertype'])],
 ['Bảo mật', $pick($detail?->security, ['tpm','secureboot','bitlocker','antivirus','firewall','status'])],
];
@endphp
<section class="card"><h2>Phần cứng và hệ thống</h2><div class="grid">
@foreach($sections as [$title,$rows])<div class="field"><div class="label">{{ $title }}</div>
@if(count($rows))@foreach($rows as $key=>$value)<div class="kv"><span>{{ $key }}</span><span>{{ $value }}</span></div>@endforeach
@else<div class="value" style="color:#718178;font-weight:400">Chưa có dữ liệu tóm tắt</div>@endif
</div>@endforeach
</div></section>

<section class="card"><h2>Phần mềm và giấy phép</h2>
@php $softwareRows=$pick($detail?->software, ['name','displayname','version','publisher','status']); $licenseRows=$pick($detail?->licenses, ['name','product','status','license_status','version']); @endphp
<div class="grid"><div class="field"><div class="label">Phần mềm</div>@forelse($softwareRows as $key=>$value)<div class="kv"><span>{{ $key }}</span><span>{{ $value }}</span></div>@empty<div class="value" style="color:#718178;font-weight:400">Chưa có dữ liệu tóm tắt</div>@endforelse</div>
<div class="field"><div class="label">Giấy phép</div>@forelse($licenseRows as $key=>$value)<div class="kv"><span>{{ $key }}</span><span>{{ $value }}</span></div>@empty<div class="value" style="color:#718178;font-weight:400">Chưa có dữ liệu tóm tắt</div>@endforelse</div></div>
</section>
@endif
</main></body></html>