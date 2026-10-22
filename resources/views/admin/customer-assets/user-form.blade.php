@extends('layouts.admin')
@section('title', $user->exists ? 'Sửa User khách hàng' : 'Tạo User khách hàng')
@section('content')
<div class="card form">
<form method="POST" action="{{ $user->exists ? route('admin.customer_assets.users.update',$user) : route('admin.customer_assets.users.store') }}">
@csrf @if($user->exists) @method('PUT') @endif
<div class="field">
<label>Khách hàng sở hữu tài sản</label>
<select class="input" required name="service_customer_id" id="service_customer_id">
<option value="">-- Chọn khách hàng --</option>
@foreach($customers as $customer)
<option value="{{ $customer->id }}" @selected((int)old('service_customer_id',$user->service_customer_id)===$customer->id)>{{ $customer->name }} ({{ $customer->code }})</option>
@endforeach
</select>
<div class="muted">User chỉ được xem tài sản thuộc khách hàng đã gán.</div>
</div>
<div class="field"><label>Họ tên</label><input class="input" required name="name" value="{{ old('name',$user->name) }}"></div>
<div class="field"><label>Email đăng nhập</label><input class="input" type="email" required name="email" value="{{ old('email',$user->email) }}"></div>
<div class="field"><label>Điện thoại</label><input class="input" name="phone" value="{{ old('phone',$user->phone) }}"></div>
<hr>
<h3>Phạm vi chi nhánh</h3>
<div class="field">
<label><input type="checkbox" id="can_access_all_branches" name="can_access_all_branches" value="1" @checked(old('can_access_all_branches',$user->can_access_all_branches))> Xem tài sản của tất cả chi nhánh thuộc khách hàng</label>
<div class="muted">Nếu không chọn, bắt buộc chọn một hoặc nhiều chi nhánh bên dưới.</div>
</div>
<div id="branch-list" class="field" style="border:1px solid #dfe7e2;border-radius:8px;padding:12px;max-height:260px;overflow:auto">
<strong>Chọn chi nhánh được phép truy cập</strong>
@foreach($branches as $branch)
<label class="branch-option" data-customer-id="{{ $branch->customer_id }}" style="display:flex;gap:8px;align-items:center;margin:9px 0">
<input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array((int)$branch->id, array_map('intval', old('branch_ids',$selectedBranchIds ?? [])), true))>
<span>{{ $branch->name }} <small>({{ $branch->code }})</small></span>
</label>
@endforeach
@if($branches->isEmpty())<p>Chưa có chi nhánh hoạt động được cấu hình.</p>@endif
</div>
<hr>
<h3>Quyền chức năng</h3>
<div class="field"><label><input type="checkbox" name="can_view_assets" value="1" @checked(old('can_view_assets',$user->can_view_assets))> Xem danh sách tài sản</label></div>
<div class="field"><label><input type="checkbox" name="can_view_audit" value="1" @checked(old('can_view_audit',$user->can_view_audit))> Xem thông tin chi tiết Audit</label></div>
<div class="field"><label><input type="checkbox" name="can_download_qr" value="1" @checked(old('can_download_qr',$user->can_download_qr))> Tạo/In QR Code tài sản</label></div>
<div class="field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$user->exists ? $user->is_active : true))> Tài khoản hoạt động</label></div>
<div class="actions"><button class="btn" type="submit">Lưu</button><a class="btn gray" href="{{ route('admin.customer_assets.users') }}">Hủy</a></div>
</form>
</div>
<script>
(function () {
  const customer = document.getElementById('service_customer_id');
  const allBranches = document.getElementById('can_access_all_branches');
  const rows = Array.from(document.querySelectorAll('.branch-option'));
  function refreshBranches() {
    rows.forEach(row => {
      const matches = row.dataset.customerId === customer.value;
      row.style.display = matches ? 'flex' : 'none';
      if (!matches) {
        const checkbox = row.querySelector('input[type="checkbox"]');
        checkbox.checked = false;
      }
    });
    const locked = allBranches.checked;
    document.getElementById('branch-list').style.opacity = locked ? '0.55' : '1';
    rows.forEach(row => { row.querySelector('input').disabled = locked || row.style.display === 'none'; });
  }
  customer.addEventListener('change', refreshBranches);
  allBranches.addEventListener('change', refreshBranches);
  refreshBranches();
})();
</script>
@endsection