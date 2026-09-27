@extends('layouts.admin')
@section('title','PC Audit - '.$audit->computer_name)
@section('content')
<style>
    .pc-show-hero{background:linear-gradient(135deg,#0f766e,#155e75);color:#fff;border-radius:16px;padding:20px 24px;margin-bottom:18px;box-shadow:0 8px 24px rgba(15,118,110,.14)}
    .pc-show-head{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}.pc-show-head h2{margin:0;font-size:22px}.pc-show-muted{color:rgba(255,255,255,.8);font-size:13px;margin-top:6px}.pc-show-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.pc-show-actions a{display:inline-flex;align-items:center;padding:8px 12px;border-radius:9px;text-decoration:none;font-size:12px;font-weight:700}.pc-show-actions .light{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);color:#fff}.pc-show-actions .edit{background:#fff;color:#0f766e}.pc-show-badge{padding:7px 12px;border-radius:999px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.24);font-weight:800;font-size:12px}
    .pc-show-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px;margin-top:18px;box-shadow:0 4px 16px rgba(15,23,42,.035)}
    .pc-show-card h3{margin:0 0 12px;color:#0f172a}.pc-show-card table{font-size:13px}.pc-show-card th{color:#475569;background:#f8fafc}.pc-show-card td,.pc-show-card th{border-bottom:1px solid #eef2f7}
</style>
@php
$status=$audit->audit_status ?: 'REVIEW';
$badge=$status==='PASS'?'#198754':($status==='FAIL'?'#dc3545':'#d39e00');
$result=$audit->audit_results ?: [];
$checks=$result['results'] ?? [];
$summaryResult=$result['summary'] ?? [];
$summary=[['Công ty',$audit->auditCode?->branch?->customer?->name],['Chi nhánh',$audit->auditCode?->branch?->name],['Audit Code',$audit->auditCode?->code],['Phòng ban',$audit->department],['Họ tên',$audit->employee_name],['Username',$audit->employee_username],['Domain',$audit->domain],['Computer Name',$audit->computer_name],['Manufacturer',$audit->manufacturer],['Model',$audit->model],['Serial Number',$audit->serial_number],['Asset Tag',$audit->asset_tag],['Last Boot',$audit->last_boot],['Uptime',$audit->uptime],['Collected At',optional($audit->collected_at)->format('d/m/Y H:i:s')]];
$sections=['RAM'=>$audit->memory,'Storage'=>$audit->storage,'Monitors'=>$audit->monitors,'GPU'=>$audit->gpu,'Battery'=>$audit->batteries,'Network'=>$audit->network,'Antivirus'=>$audit->antivirus,'BitLocker'=>$audit->bitlocker,'Firewall'=>$audit->firewall,'Licenses'=>$audit->licenses,'Software'=>$audit->software];
@endphp

<div class="pc-show-hero">
    <div class="pc-show-head">
        <div>
            <h2>PC Audit: {{ $audit->computer_name ?: 'N/A' }}</h2>
            <div class="pc-show-muted">{{ $audit->auditCode?->branch?->customer?->name }} / {{ $audit->auditCode?->branch?->name }} / {{ $audit->auditCode?->code }}</div>
        </div>
        <div class="pc-show-actions">
            <span class="pc-show-badge">{{ $status }} · {{ $audit->audit_score !== null ? $audit->audit_score.'%' : '—' }}</span>
            @if(auth()->user()->hasPermission('pc_audit.edit'))<a class="edit" href="{{ route('admin.pc_audit.edit',$audit) }}">✏ Chỉnh sửa</a>@endif
            <a class="light" href="{{ route('admin.pc_audit.index') }}">← Danh sách</a>
        </div>
    </div>
</div>

<div class="pc-show-card"><h3>Kết quả Audit</h3><p><strong>{{ $summaryResult['pass'] ?? 0 }}</strong> PASS &nbsp; <strong>{{ $summaryResult['review'] ?? 0 }}</strong> REVIEW &nbsp; <strong>{{ $summaryResult['fail'] ?? 0 }}</strong> FAIL</p><table style="width:100%;border-collapse:collapse"><thead><tr><th style="text-align:left;padding:8px">Tiêu chí</th><th style="text-align:left;padding:8px">Kết quả</th><th style="text-align:left;padding:8px">Ghi chú</th></tr></thead><tbody>@forelse($checks as $check)@php $s=$check['status'] ?? 'REVIEW'; $c=$s==='PASS'?'#198754':($s==='FAIL'?'#dc3545':'#d39e00'); @endphp<tr><td style="padding:8px">{{ $check['label'] ?? $check['key'] ?? '' }}</td><td style="padding:8px;color:{{ $c }};font-weight:700">{{ $s }}</td><td style="padding:8px">{{ $check['message'] ?? '' }}</td></tr>@empty<tr><td colspan="3" style="padding:8px">Chưa có kết quả Audit Engine.</td></tr>@endforelse</tbody></table><div class="muted">Audit Engine {{ $result['engine_version'] ?? '—' }}</div></div>
<div class="pc-show-card"><h3>Thông tin máy</h3><table style="width:100%"><tbody>@foreach($summary as $row)<tr><th style="width:220px;text-align:left;padding:8px">{{ $row[0] }}</th><td style="padding:8px">{{ is_scalar($row[1]) ? $row[1] : json_encode($row[1], JSON_UNESCAPED_UNICODE) }}</td></tr>@endforeach</tbody></table></div>
<div class="pc-show-card"><h3>Thông tin hệ thống</h3><table style="width:100%"><tbody>@foreach(['Mainboard'=>$audit->mainboard,'BIOS'=>$audit->bios,'Operating System'=>$audit->operating_system,'Windows Update'=>$audit->windows_update,'TPM'=>$audit->tpm,'Secure Boot'=>$audit->secure_boot] as $label=>$value)<tr><th style="width:220px;text-align:left;padding:8px">{{ $label }}</th><td style="padding:8px"><pre style="white-space:pre-wrap;margin:0">{{ json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td></tr>@endforeach</tbody></table></div>
@foreach($sections as $title=>$items)<div class="pc-show-card"><h3>{{ $title }} ({{ $items->count() }})</h3>@if($items->isEmpty())<p class="muted">Không có dữ liệu.</p>@else<table style="width:100%;border-collapse:collapse"><thead><tr>@foreach(array_keys($items->first()->toArray()) as $field) @if(!in_array($field,['id','pc_audit_id','created_at','updated_at']))<th style="text-align:left;padding:8px">{{ $field }}</th>@endif @endforeach</tr></thead><tbody>@foreach($items as $item)<tr>@foreach($item->toArray() as $field=>$value) @if(!in_array($field,['id','pc_audit_id','created_at','updated_at']))<td style="padding:8px;vertical-align:top">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</td>@endif @endforeach</tr>@endforeach</tbody></table>@endif</div>@endforeach
@endsection
