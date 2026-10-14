@extends('layouts.admin')
@section('title','Ticket '.$item->ticket_code)
@section('content')
@php
$statusLabels=['pending_hod'=>'Chờ HOD phê duyệt','pending_it_review'=>'Chờ IT đánh giá','pending_purchase_approval'=>'Chờ phê duyệt mua sắm','approved_for_installation'=>'Đã duyệt – có thể cài đặt','installing'=>'Đang cài đặt','awaiting_acceptance'=>'Chờ người dùng nghiệm thu','pending_closure'=>'Chờ kiểm tra hồ sơ đóng','exception_pending'=>'Chờ duyệt ngoại lệ','returned'=>'Yêu cầu bổ sung','rejected'=>'Từ chối','closed'=>'Đã đóng'];
$assessment=$item->assessments->last(); $install=$item->installations->last(); $checklist=$item->checklists->keyBy('item_key');
@endphp
<div class="card" style="margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap"><div><div class="muted">Mã Ticket</div><h2 style="margin:4px 0">{{ $item->ticket_code }}</h2><div>{{ $item->software_name }} · {{ $item->device_name }}</div></div><span class="btn gray">{{ $statusLabels[$item->status] ?? $item->status }}</span></div>
    <hr style="border:0;border-top:1px solid #e8eee9;margin:18px 0">
    <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <div><div class="muted">Người yêu cầu</div><strong>{{ $item->requester?->name }}</strong></div>
        <div><div class="muted">HOD</div><strong>{{ $item->hod?->name ?? '—' }}</strong></div>
        <div><div class="muted">IT phụ trách</div><strong>{{ $item->assignedIt?->name ?? 'Chưa phân công' }}</strong></div>
        <div><div class="muted">Nhánh</div><strong>{{ strtoupper($item->branch_type) }}</strong></div>
        <div><div class="muted">Loại quyền</div><strong>{{ ['readonly'=>'Read-only/View-only','edit'=>'Chỉnh sửa','other'=>'Khác'][$item->permission_type] ?? $item->permission_type }}</strong></div>
        <div><div class="muted">Ngày tạo</div><strong>{{ $item->created_at?->format('d/m/Y H:i') }}</strong></div>
    </div>
    <div style="margin-top:16px"><div class="muted">Mục đích nghiệp vụ</div><p>{{ $item->business_purpose }}</p></div>
    @if($item->data_access)<div><div class="muted">Phạm vi dữ liệu/hệ thống/mạng</div><p>{{ $item->data_access }}</p></div>@endif
    @if($item->hod_comment)<div class="info"><strong>Ý kiến HOD:</strong> {{ $item->hod_comment }}</div>@endif
    @if($item->it_assessment)<div class="info"><strong>Nhận xét IT:</strong> {{ $item->it_assessment }}</div>@endif
</div>

@if($item->status==='pending_hod' && (auth()->id()===$item->hod_id || auth()->user()->hasPermission('software.approve')))
<div class="card" style="margin-bottom:16px"><h3>Phê duyệt nhu cầu – HOD</h3>
<form method="post" action="{{ route('admin.software_management.hod_decision',$item) }}">@csrf
<div class="field"><label>Quyết định *</label><select name="decision" class="input" required><option value="approved">Đồng ý</option><option value="returned">Yêu cầu bổ sung</option><option value="rejected">Từ chối</option></select></div>
<div class="field"><label>Ý kiến / lý do *</label><textarea name="comment" class="input" rows="2" required></textarea></div><button class="btn" type="submit">Ghi nhận quyết định</button></form></div>
@endif

@if(in_array($item->status,['pending_it_review','returned'],true) && auth()->user()->hasPermission('software.assess'))
<div class="card" style="margin-bottom:16px"><h3>FR-16-02 – Đánh giá kỹ thuật, bảo mật và giấy phép</h3>
<form method="post" action="{{ route('admin.software_management.assessment',$item) }}">@csrf
<div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
<div class="field"><label>Nguồn cài đặt chính thức (URL)</label><input class="input" type="url" name="source_url" value="{{ old('source_url',$assessment?->source_url) }}"></div>
<div class="field"><label>Tương thích hệ điều hành/thiết bị *</label><select class="input" name="compatibility_result" required><option value="pass">Đạt</option><option value="clarify">Cần làm rõ</option><option value="fail">Không đạt</option></select></div>
<div class="field"><label>Yêu cầu quyền quản trị *</label><select class="input" name="admin_rights_required" required><option value="0">Không</option><option value="1">Có</option></select></div>
<div class="field"><label>Rủi ro bảo mật *</label><select class="input" name="security_risk" required><option value="accepted">Chấp nhận</option><option value="mitigate">Cần xử lý/giảm thiểu</option><option value="reject">Từ chối</option></select></div>
<div class="field"><label>Loại giấy phép</label><input class="input" name="license_type" value="{{ old('license_type',$assessment?->license_type) }}"></div>
<div class="field"><label>Số lượng giấy phép</label><input class="input" type="number" min="1" name="license_quantity" value="{{ old('license_quantity',$assessment?->license_quantity) }}"></div>
<div class="field"><label>Thời hạn giấy phép</label><input class="input" type="date" name="license_until" value="{{ old('license_until',$assessment?->license_until?->format('Y-m-d')) }}"></div>
<div class="field"><label>Nhánh xử lý *</label><select class="input" name="branch_type" required><option value="borrow" @selected($item->branch_type==='borrow')>Borrow</option><option value="buy" @selected($item->branch_type==='buy')>Buy</option></select></div>
</div>
<div class="field"><label>Phạm vi truy cập dữ liệu/mạng</label><textarea class="input" name="data_access_scope" rows="2">{{ old('data_access_scope',$item->data_access) }}</textarea></div>
<div class="field"><label>Bằng chứng quyền sử dụng hợp pháp (bắt buộc với Borrow)</label><textarea class="input" name="license_evidence" rows="2" placeholder="Mã hồ sơ/bản quyền/số lượng được cấp; tuyệt đối không nhập product key hoặc secret">{{ old('license_evidence',$assessment?->license_evidence) }}</textarea></div>
<div class="field"><label>Kết luận IT *</label><select class="input" name="it_conclusion" required><option value="approved">Chấp thuận kỹ thuật</option><option value="conditional">Chấp thuận có điều kiện</option><option value="need_info">Cần bổ sung thông tin</option><option value="rejected">Từ chối</option></select></div>
<div class="field"><label>Điều kiện/biện pháp kiểm soát</label><textarea class="input" name="conditions" rows="2">{{ old('conditions',$assessment?->conditions) }}</textarea></div>
<div class="field"><label>Ghi chú đánh giá</label><textarea class="input" name="notes" rows="2">{{ old('notes',$assessment?->notes) }}</textarea></div>
<div class="field"><label>IT phụ trách</label><select class="input" name="assigned_it_id"><option value="">Tôi phụ trách</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
<button class="btn" type="submit">Lưu đánh giá</button></form></div>
@endif

@if($item->status==='pending_purchase_approval' && auth()->user()->hasPermission('software.purchase_approve'))
<div class="card" style="margin-bottom:16px"><h3>FR-16-03 – Phê duyệt mua sắm</h3><p class="muted">Đính kèm báo giá, đề xuất nhà cung cấp, đơn hàng/hợp đồng và chứng từ trước khi đặt mua.</p>
<form method="post" action="{{ route('admin.software_management.purchase_decision',$item) }}">@csrf
<div class="field"><label>Quyết định *</label><select class="input" name="decision" required><option value="approved">Phê duyệt</option><option value="returned">Yêu cầu bổ sung</option><option value="rejected">Từ chối</option></select></div>
<div class="field"><label>Chi phí được duyệt (VND)</label><input class="input" type="number" min="0" step="1000" name="approved_cost"></div>
<div class="field"><label>Ý kiến *</label><textarea class="input" name="comment" required rows="2"></textarea></div><button class="btn">Ghi nhận</button></form></div>
@endif

@if(in_array($item->status,['approved_for_installation','installing'],true) && auth()->user()->hasPermission('software.install'))
<div class="card" style="margin-bottom:16px"><h3>FR-16-04 – Ghi nhận cài đặt và kiểm tra</h3>
<form method="post" action="{{ route('admin.software_management.installation',$item) }}">@csrf
<div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
<div class="field"><label>Bắt đầu *</label><input class="input" type="datetime-local" name="started_at" required></div>
<div class="field"><label>Kết thúc</label><input class="input" type="datetime-local" name="finished_at"></div>
<div class="field"><label>Kết quả cài đặt *</label><select class="input" name="result" required><option value="success">Thành công</option><option value="conditional">Có điều kiện</option><option value="failed">Không thành công</option></select></div>
<div class="field"><label>Kiểm tra chức năng *</label><select class="input" name="functionality_check" required><option value="pass">Đạt</option><option value="fail">Không đạt</option></select></div>
<div class="field"><label>Kiểm tra bảo mật/cấu hình/quyền *</label><select class="input" name="security_check" required><option value="pass">Đạt</option><option value="fail">Không đạt</option></select></div>
<div class="field"><label>Read-only/View-only *</label><select class="input" name="readonly_check" required><option value="na">Không áp dụng</option><option value="pass">Đạt</option><option value="fail">Không đạt</option></select></div>
</div>
<div class="field"><label>Lỗi/biện pháp xử lý</label><textarea class="input" name="issues" rows="2"></textarea></div>
<div class="field"><label>Phương án quay lui</label><textarea class="input" name="rollback_plan" rows="2"></textarea></div>
<div class="field"><label>Ghi chú</label><textarea class="input" name="notes" rows="2"></textarea></div>
<button class="btn">Lưu kết quả cài đặt và chuyển nghiệm thu</button></form></div>
@endif

@if($item->status==='awaiting_acceptance' && (auth()->id()===$item->requester_id || auth()->id()===$item->hod_id || auth()->user()->hasPermission('software.approve')))
<div class="card" style="margin-bottom:16px"><h3>Xác nhận người dùng/HOD</h3><form method="post" action="{{ route('admin.software_management.acceptance',$item) }}">@csrf
<div class="field"><label>Kết quả nghiệm thu *</label><select name="acceptance" class="input" required><option value="accepted">Nghiệm thu</option><option value="not_accepted">Chưa nghiệm thu</option></select></div>
<div class="field"><label>Ý kiến</label><textarea name="comment" class="input" rows="2"></textarea></div><button class="btn">Ghi nhận nghiệm thu</button></form></div>
@endif

@if($item->status==='pending_closure' && auth()->user()->hasPermission('software.close'))
<div class="card" style="margin-bottom:16px"><h3>FR-16-05 – Checklist trước khi đóng Ticket</h3><form method="post" action="{{ route('admin.software_management.checklist',$item) }}">@csrf
@foreach($closeItems as $key=>$label)<div style="padding:12px 0;border-bottom:1px solid #e8eee9"><label><input type="checkbox" name="items[{{ $key }}][passed]" value="1" @checked($checklist->get($key)?->passed)> {{ $label }}</label><input class="input" name="items[{{ $key }}][evidence]" value="{{ $checklist->get($key)?->evidence }}" placeholder="Bằng chứng / ghi chú"></div>@endforeach
<button class="btn" style="margin-top:14px">Lưu checklist</button></form>
<form method="post" action="{{ route('admin.software_management.close',$item) }}" style="margin-top:12px" onsubmit="return confirm('Đóng Ticket sau khi kiểm tra toàn bộ bằng chứng?')">@csrf<button class="btn" type="submit">Kiểm tra và đóng Ticket</button></form></div>
@endif

<div class="card" style="margin-bottom:16px"><h3>FR-16-06 – Phiếu ngoại lệ</h3>
@if(auth()->user()->hasPermission('software.exception.create'))
<form method="post" action="{{ route('admin.software_management.exceptions.store',$item) }}">@csrf
<div class="field"><label>Mô tả ngoại lệ *</label><textarea class="input" name="description" required rows="2"></textarea></div>
<div class="field"><label>Lý do nghiệp vụ *</label><textarea class="input" name="business_reason" required rows="2"></textarea></div>
<div class="field"><label>Rủi ro / tác động *</label><textarea class="input" name="risk_impact" required rows="2"></textarea></div>
<div class="field"><label>Phạm vi / thiết bị / người dùng *</label><textarea class="input" name="scope" required rows="2"></textarea></div>
<div class="field"><label>Biện pháp giảm thiểu *</label><textarea class="input" name="mitigation" required rows="2"></textarea></div>
<div class="field"><label>Kế hoạch quay lui</label><textarea class="input" name="rollback_plan" rows="2"></textarea></div>
<div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr))"><div class="field"><label>Hạn ngoại lệ *</label><input class="input" type="date" name="expires_at" required></div><div class="field"><label>Người chịu trách nhiệm *</label><select class="input" name="owner_id" required>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div></div>
<div class="field"><label>Ý kiến IT *</label><textarea class="input" name="it_opinion" required rows="2"></textarea></div><button class="btn">Gửi phê duyệt ngoại lệ</button></form>
@endif
@forelse($item->exceptions as $exception)
<div class="info" style="margin-top:12px"><strong>{{ $exception->approval_status }}</strong> · Hết hạn {{ $exception->expires_at?->format('d/m/Y') }}<div>{{ $exception->description }}</div>
@if($exception->approval_status==='pending' && auth()->user()->hasPermission('software.exception.approve'))
<form method="post" action="{{ route('admin.software_management.exceptions.decision',$exception) }}" class="actions" style="margin-top:8px">@csrf<select class="input" style="max-width:180px;margin:0" name="decision"><option value="approved">Phê duyệt</option><option value="rejected">Từ chối</option></select><input class="input" style="max-width:300px;margin:0" name="comment" required placeholder="Ý kiến/phê duyệt"><button class="btn">Ghi quyết định</button></form>
@endif
</div>
@empty<div class="muted">Chưa có phiếu ngoại lệ.</div>@endforelse
</div>

<div class="card" style="margin-bottom:16px"><h3>Hồ sơ đính kèm</h3>
@if(auth()->user()->hasPermission('software.attachments.manage'))<form method="post" enctype="multipart/form-data" action="{{ route('admin.software_management.attachments.store',$item) }}" class="actions" style="margin-bottom:14px">@csrf<select class="input" name="category" style="max-width:180px"><option value="assessment">Đánh giá IT</option><option value="quote">Báo giá</option><option value="purchase">Mua sắm</option><option value="license">Giấy phép</option><option value="acceptance">Nghiệm thu</option><option value="exception">Ngoại lệ</option><option value="other">Khác</option></select><input class="input" style="max-width:320px" type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" required><button class="btn">Tải lên</button></form>@endif
@forelse($item->attachments as $file)<div style="padding:8px 0;border-bottom:1px solid #e8eee9">{{ $file->original_name }} <span class="muted">· {{ $file->category }} · {{ number_format($file->size_bytes/1024,1) }} KB</span> @if(auth()->user()->hasPermission('software.attachments.view'))<a href="{{ route('admin.software_management.attachments.download',$file) }}">Tải về</a>@endif</div>@empty<div class="muted">Chưa có tệp đính kèm.</div>@endforelse
</div>
<div class="card"><h3>Lịch sử phê duyệt</h3><div class="table-wrap"><table class="table"><thead><tr><th>Bước</th><th>Quyết định</th><th>Người duyệt</th><th>Thời gian</th><th>Ý kiến</th></tr></thead><tbody>@forelse($item->approvals as $approval)<tr><td>{{ $approval->stage }}</td><td>{{ $approval->decision }}</td><td>{{ $approval->approver_id ? (\App\Models\User::find($approval->approver_id)?->name ?? '—') : 'Chờ duyệt' }}</td><td>{{ $approval->decided_at?->format('d/m/Y H:i') ?? '—' }}</td><td>{{ $approval->comment }}</td></tr>@empty<tr><td colspan="5" class="muted">Chưa có lịch sử phê duyệt.</td></tr>@endforelse</tbody></table></div></div>
<div style="margin-top:16px"><a class="btn gray" href="{{ route('admin.software_management.index') }}">← Quay lại danh sách</a></div>
@endsection