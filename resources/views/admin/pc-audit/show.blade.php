@extends('layouts.admin')
@section('title','PC Audit - '.$audit->computer_name)
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
        <div>
            <h2>PC Audit: {{ $audit->computer_name ?: 'N/A' }}</h2>
            <div class="muted">{{ $audit->auditCode?->branch?->customer?->name }} / {{ $audit->auditCode?->branch?->name }} / {{ $audit->auditCode?->code }}</div>
        </div>
        <a class="button" href="{{ route('admin.pc_audit.index') }}">← Danh sách</a>
    </div>
</div>

@php
$summary = [
 ['Công ty',$audit->auditCode?->branch?->customer?->name],['Chi nhánh',$audit->auditCode?->branch?->name],['Audit Code',$audit->auditCode?->code],
 ['Phòng ban',$audit->department],['Họ tên',$audit->employee_name],['Username',$audit->employee_username],['Domain',$audit->domain],
 ['Computer Name',$audit->computer_name],['Manufacturer',$audit->manufacturer],['Model',$audit->model],['Serial Number',$audit->serial_number],['Asset Tag',$audit->asset_tag],
 ['Last Boot',$audit->last_boot],['Uptime',$audit->uptime],['Collected At',optional($audit->collected_at)->format('d/m/Y H:i:s')],
];
$sections = ['RAM'=>$audit->memory,'Storage'=>$audit->storage,'Monitors'=>$audit->monitors,'GPU'=>$audit->gpu,'Battery'=>$audit->batteries,'Network'=>$audit->network,'Antivirus'=>$audit->antivirus,'BitLocker'=>$audit->bitlocker,'Firewall'=>$audit->firewall,'Licenses'=>$audit->licenses,'Software'=>$audit->software];
@endphp

<div class="card" style="margin-top:18px">
<h3>Thông tin máy</h3>
<table style="width:100%"><tbody>@foreach($summary as $row)<tr><th style="width:220px;text-align:left;padding:7px">{{ $row[0] }}</th><td style="padding:7px">{{ is_scalar($row[1]) ? $row[1] : json_encode($row[1], JSON_UNESCAPED_UNICODE) }}</td></tr>@endforeach</tbody></table>
</div>

<div class="card" style="margin-top:18px">
<h3>Thông tin hệ thống</h3>
<table style="width:100%"><tbody>
@foreach(['Mainboard'=>$audit->mainboard,'BIOS'=>$audit->bios,'Operating System'=>$audit->operating_system,'Windows Update'=>$audit->windows_update,'TPM'=>$audit->tpm,'Secure Boot'=>$audit->secure_boot] as $label=>$value)
<tr><th style="width:220px;text-align:left;padding:7px">{{ $label }}</th><td style="padding:7px"><pre style="white-space:pre-wrap;margin:0">{{ json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td></tr>
@endforeach
</tbody></table>
</div>

@foreach($sections as $title=>$items)
<div class="card" style="margin-top:18px">
<h3>{{ $title }} ({{ $items->count() }})</h3>
@if($items->isEmpty())<p class="muted">Không có dữ liệu.</p>
@else
<table style="width:100%;border-collapse:collapse"><thead><tr>@foreach(array_keys($items->first()->toArray()) as $field) @if(!in_array($field,['id','pc_audit_id','created_at','updated_at']))<th style="text-align:left;padding:7px">{{ $field }}</th>@endif @endforeach</tr></thead><tbody>
@foreach($items as $item)<tr>@foreach($item->toArray() as $field=>$value) @if(!in_array($field,['id','pc_audit_id','created_at','updated_at']))<td style="padding:7px;vertical-align:top">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</td>@endif @endforeach</tr>@endforeach
</tbody></table>
@endif
</div>
@endforeach
@endsection
