@extends('layouts.admin')
@section('title','Danh mục phần mềm và giấy phép – REG-16-01')
@section('content')
<div class="card" style="margin-bottom:16px">
<form method="get" class="actions" style="margin-bottom:14px"><input class="input" style="max-width:360px;margin:0" name="search" value="{{ request('search') }}" placeholder="Tên phần mềm / nhà cung cấp"><button class="btn gray">Tìm kiếm</button><a class="btn gray" href="{{ route('admin.software_management.licenses') }}">Xóa lọc</a></form>
<div class="table-wrap"><table class="table"><thead><tr><th>Phần mềm</th><th>Nhà cung cấp / phiên bản</th><th>Loại giấy phép</th><th>Số lượng</th><th>Người dùng / thiết bị</th><th>Hết hạn</th><th>Trạng thái</th><th>Ticket</th></tr></thead><tbody>
@forelse($licenses as $license)<tr><td><strong>{{ $license->software_name }}</strong><div class="muted">{{ $license->asset_code }}</div></td><td>{{ $license->vendor }}<div class="muted">{{ $license->version }}</div></td><td>{{ $license->license_type }}</td><td>{{ $license->quantity }}</td><td>{{ $license->assigned_user }}<div class="muted">{{ $license->device_name }}</div></td><td>{{ $license->expires_at?->format('d/m/Y') ?? 'Không xác định' }} @if($license->expires_at && $license->expires_at->lte(now()->addDays(30)))<div class="muted">Cần rà soát</div>@endif</td><td>{{ $license->status }}</td><td>{{ $license->ticket_code }}</td></tr>@empty<tr><td colspan="8" class="muted">Chưa có giấy phép trong danh mục.</td></tr>@endforelse
</tbody></table></div><div style="margin-top:16px">{{ $licenses->links() }}</div></div>
@if(auth()->user()->hasPermission('software.licenses.manage'))
<div class="card"><h3>Thêm mục giấy phép</h3><form method="post" action="{{ route('admin.software_management.licenses.store') }}" class="form">@csrf
<div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
<div class="field"><label>Tên phần mềm *</label><input class="input" name="software_name" required></div><div class="field"><label>Nhà cung cấp</label><input class="input" name="vendor"></div>
<div class="field"><label>Phiên bản</label><input class="input" name="version"></div><div class="field"><label>Loại giấy phép *</label><input class="input" name="license_type" required placeholder="Subscription / Perpetual..."></div>
<div class="field"><label>Số lượng *</label><input class="input" name="quantity" type="number" min="1" value="1" required></div><div class="field"><label>Người dùng</label><input class="input" name="assigned_user"></div>
<div class="field"><label>Thiết bị</label><input class="input" name="device_name"></div><div class="field"><label>Mã tài sản</label><input class="input" name="asset_code"></div>
<div class="field"><label>Ngày cài/cấp</label><input class="input" name="installed_at" type="date"></div><div class="field"><label>Ngày hết hạn</label><input class="input" name="expires_at" type="date"></div>
<div class="field"><label>Mã Ticket liên quan</label><input class="input" name="ticket_code"></div><div class="field"><label>Trạng thái *</label><select class="input" name="status"><option value="active">Đang sử dụng</option><option value="pending">Chờ kích hoạt</option><option value="expired">Hết hạn</option><option value="revoked">Thu hồi</option></select></div>
</div><div class="field"><label>Ghi chú / mã hồ sơ chứng minh (không ghi product key/secret)</label><textarea class="input" name="notes" rows="2"></textarea></div><button class="btn">Thêm vào danh mục</button></form></div>
@endif
@endsection