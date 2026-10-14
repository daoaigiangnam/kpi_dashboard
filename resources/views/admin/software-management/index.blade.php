@extends('layouts.admin')
@section('title','Quản lý cài đặt phần mềm')
@section('content')
<div class="grid" style="margin-bottom:18px">
    <div class="card"><div class="muted">Tổng yêu cầu</div><h2>{{ number_format($stats['total']) }}</h2></div>
    <div class="card"><div class="muted">Chờ phê duyệt/xử lý</div><h2>{{ number_format($stats['pending']) }}</h2></div>
    <div class="card"><div class="muted">Cài đặt/nghiệm thu</div><h2>{{ number_format($stats['installing']) }}</h2></div>
    <div class="card"><div class="muted">Hồ sơ cần chú ý</div><h2>{{ number_format($stats['blocked']) }}</h2></div>
</div>
<div class="card" style="margin-bottom:18px">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
        <div><strong>Danh sách Ticket phần mềm</strong><div class="muted">Theo dõi quy trình QP-HRD-16 và các biểu mẫu FR-16.</div></div>
        @if(auth()->user()->hasPermission('software.requests.create'))<a class="btn" href="{{ route('admin.software_management.create') }}">+ Tạo yêu cầu</a>@endif
    </div>
    <form method="get" class="actions" style="margin-bottom:14px">
        <input class="input" style="max-width:300px;margin:0" name="search" value="{{ request('search') }}" placeholder="Mã Ticket, phần mềm, thiết bị">
        <select class="input" style="max-width:210px;margin:0" name="status"><option value="">Tất cả trạng thái</option>
            @foreach(['pending_hod'=>'Chờ HOD','pending_it_review'=>'Chờ IT đánh giá','pending_purchase_approval'=>'Chờ duyệt mua','approved_for_installation'=>'Đã duyệt cài đặt','installing'=>'Đang cài đặt','awaiting_acceptance'=>'Chờ nghiệm thu','pending_closure'=>'Chờ đóng Ticket','exception_pending'=>'Chờ duyệt ngoại lệ','returned'=>'Yêu cầu bổ sung','rejected'=>'Từ chối','closed'=>'Đã đóng'] as $key=>$label)
            <option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>
            @endforeach
        </select>
        <select class="input" style="max-width:160px;margin:0" name="branch_type"><option value="">Borrow / Buy</option><option value="borrow" @selected(request('branch_type')==='borrow')>Borrow</option><option value="buy" @selected(request('branch_type')==='buy')>Buy</option></select>
        <button class="btn gray">Lọc</button>
        <a class="btn gray" href="{{ route('admin.software_management.index') }}">Xóa lọc</a>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Mã Ticket</th><th>Phần mềm / Thiết bị</th><th>Người yêu cầu</th><th>Loại</th><th>Trạng thái</th><th>IT phụ trách</th><th></th></tr></thead>
        <tbody>
        @forelse($requests as $item)
        <tr>
            <td><strong><a href="{{ route('admin.software_management.show',$item) }}">{{ $item->ticket_code }}</a></strong><div class="muted">{{ $item->created_at?->format('d/m/Y H:i') }}</div></td>
            <td>{{ $item->software_name }}<div class="muted">{{ $item->device_name }} @if($item->asset_code)· {{ $item->asset_code }}@endif</div></td>
            <td>{{ $item->requester?->name ?? '—' }}<div class="muted">{{ $item->department }}</div></td>
            <td><span class="btn {{ $item->branch_type==='buy'?'gray':'' }}" style="padding:4px 8px">{{ strtoupper($item->branch_type) }}</span></td>
            <td>{{ ['pending_hod'=>'Chờ HOD','pending_it_review'=>'Chờ IT đánh giá','pending_purchase_approval'=>'Chờ duyệt mua','approved_for_installation'=>'Đã duyệt cài đặt','installing'=>'Đang cài đặt','awaiting_acceptance'=>'Chờ nghiệm thu','pending_closure'=>'Chờ đóng Ticket','exception_pending'=>'Chờ duyệt ngoại lệ','returned'=>'Cần bổ sung','rejected'=>'Từ chối','closed'=>'Đã đóng'][$item->status] ?? $item->status }}</td>
            <td>{{ $item->assignedIt?->name ?? '—' }}</td>
            <td><a class="btn gray" href="{{ route('admin.software_management.show',$item) }}">Mở</a></td>
        </tr>
        @empty<tr><td colspan="7" class="muted">Chưa có yêu cầu phần mềm.</td></tr>@endforelse
        </tbody>
    </table></div>
    <div style="margin-top:16px">{{ $requests->links() }}</div>
</div>
<div class="card"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><div><strong>Danh mục phần mềm và giấy phép (REG-16-01)</strong><div class="muted">Theo dõi hạn cấp phép và tình trạng sử dụng.</div></div>@if(auth()->user()->hasPermission('software.licenses.view'))<a class="btn gray" href="{{ route('admin.software_management.licenses') }}">Mở danh mục giấy phép ({{ $stats['expiring'] }} sắp hết hạn trong 30 ngày)</a>@endif</div></div>
@endsection