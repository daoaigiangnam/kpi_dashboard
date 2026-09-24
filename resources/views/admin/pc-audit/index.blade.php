@extends('layouts.admin')
@section('title','PC Audit')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><h2>PC Audit</h2><div style="display:flex;gap:8px"><a class="button" href="{{ route('admin.pc_audit.codes') }}">Audit Code</a><a class="button" href="{{ route('admin.pc_audit.recipients') }}">Email Customer</a><a class="button" href="{{ route('admin.pc_audit.settings') }}">Cấu hình Tool</a></div></div>
    <form method="GET" action="{{ route('admin.pc_audit.index') }}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-top:12px">
        <div style="min-width:320px;flex:1"><label>Tìm kiếm</label><input name="search" value="{{ $search }}" placeholder="Computer Name / Serial / Họ tên / Phòng ban / Code"></div>
        <div><label>Customer</label><select name="customer_id"><option value="">Tất cả</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string)$customerId===(string)$customer->id)>{{ $customer->name }}</option>@endforeach</select></div>
        <button type="submit">Tìm kiếm</button><a class="button" href="{{ route('admin.pc_audit.index') }}">Xóa lọc</a>
    </form>
</div>

<div class="card" style="margin-top:18px">
<form method="GET" action="{{ route('admin.pc_audit.export') }}" id="export-form">
<table style="width:100%;border-collapse:collapse">
<thead><tr><th></th><th>Khách hàng</th><th>Chi nhánh</th><th>Code</th><th>Computer</th><th>Serial</th><th>Họ tên</th><th>Phòng ban</th><th>Ngày Audit</th><th></th></tr></thead>
<tbody>
@forelse($audits as $audit)
<tr><td><input type="checkbox" name="ids[]" value="{{ $audit->id }}"></td><td>{{ $audit->auditCode?->branch?->customer?->name }}</td><td>{{ $audit->auditCode?->branch?->name }}</td><td>{{ $audit->auditCode?->code }}</td><td>{{ $audit->computer_name }}</td><td>{{ $audit->serial_number }}</td><td>{{ $audit->employee_name }}</td><td>{{ $audit->department }}</td><td>{{ optional($audit->collected_at)->format('d/m/Y H:i') }}</td><td><a class="button" href="{{ route('admin.pc_audit.show', $audit) }}">Xem</a></td></tr>
@empty
<tr><td colspan="10">Chưa có dữ liệu Audit.</td></tr>
@endforelse
</tbody></table>
<div style="margin-top:14px;display:flex;gap:10px;align-items:center"><button type="submit">Xuất Excel các máy đã chọn</button><span class="muted">Tối đa 200 máy/lần. Mỗi máy được xuất thành một sheet đầy đủ.</span></div>
</form><div style="margin-top:16px">{{ $audits->links() }}</div>
</div>
@endsection
