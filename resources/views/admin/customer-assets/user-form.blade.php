@extends('layouts.admin')
@section('title', $user->exists ? 'Sửa User khách hàng' : 'Tạo User khách hàng')
@section('content')
<div class="card form"><form method="POST" action="{{ $user->exists ? route('admin.customer_assets.users.update',$user) : route('admin.customer_assets.users.store') }}">@csrf @if($user->exists) @method('PUT') @endif
<div class="field"><label>Khách hàng sở hữu tài sản</label><select class="input" required name="service_customer_id"><option value="">-- Chọn khách hàng --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((int)old('service_customer_id',$user->service_customer_id)===$customer->id)>{{ $customer->name }} ({{ $customer->code }})</option>@endforeach</select><div class="muted">Tài khoản chỉ xem dữ liệu PC Audit thuộc khách hàng này.</div></div>
<div class="field"><label>Họ tên</label><input class="input" required name="name" value="{{ old('name',$user->name) }}"></div>
<div class="field"><label>Email đăng nhập</label><input class="input" type="email" required name="email" value="{{ old('email',$user->email) }}"></div>
<div class="field"><label>Điện thoại</label><input class="input" name="phone" value="{{ old('phone',$user->phone) }}"></div>
<div class="field"><label><input type="checkbox" name="can_view_assets" value="1" @checked(old('can_view_assets',$user->can_view_assets))> Xem Danh sách tài sản</label></div>
<div class="field"><label><input type="checkbox" name="can_view_audit" value="1" @checked(old('can_view_audit',$user->can_view_audit))> Xem thông tin chi tiết Audit</label></div>
<div class="field"><label><input type="checkbox" name="can_download_qr" value="1" @checked(old('can_download_qr',$user->can_download_qr))> Tạo/In QR Code tài sản</label></div>
<div class="field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$user->exists ? $user->is_active : true))> Tài khoản hoạt động</label></div>
<div class="actions"><button class="btn" type="submit">Lưu</button><a class="btn gray" href="{{ route('admin.customer_assets.users') }}">Hủy</a></div>
</form></div>
@endsection