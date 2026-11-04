@extends('layouts.admin')
@section('title', $customer->exists ? 'Chỉnh sửa Customer' : 'Tạo Customer')
@section('content')
<style>
.customer-form{max-width:1000px}
.customer-form .section{background:#fff;border:1px solid #e1e9e4;border-radius:14px;padding:20px 22px;margin-bottom:16px;box-shadow:0 2px 8px rgba(20,70,50,.03)}
.customer-form .section h3{margin:0 0 7px;font-size:17px;color:#16352a}
.customer-form .section-note{color:#66736b;font-size:13px;line-height:1.55;margin-bottom:16px}
.customer-form .field label{display:block;font-weight:600;margin-bottom:6px;color:#26352f}
.customer-form .input{width:100%;box-sizing:border-box}
.person-card{border:1px solid #cfe2d8;border-radius:10px;padding:14px 16px;background:#f7fbf9}
.person-card strong{display:block;font-size:15px;color:#14382c}.person-card small{display:block;color:#66736b;margin-top:4px}
.person-card .badge{display:inline-block;margin-top:8px;padding:4px 9px;border-radius:999px;background:#e8f5ef;color:#08745c;font-size:12px;font-weight:600}
.sales-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.toggle-row{display:flex;align-items:center;gap:10px;margin-top:10px}
.toggle-row label{margin:0!important;display:inline-flex!important;align-items:center;gap:7px;font-weight:600}
.alert-note{background:#f7fbfa;border:1px solid #dcebe4;border-radius:10px;padding:14px 16px;color:#496058;font-size:13px;line-height:1.55}
.actions{display:flex;gap:8px;align-items:center}
@media(max-width:760px){.sales-grid{grid-template-columns:1fr}}
</style>

<div class="customer-form">
<form method="post" action="{{ $customer->exists ? route('admin.service_customers.update',$customer) : route('admin.service_customers.store') }}">
@csrf @if($customer->exists) @method('PUT') @endif

<div class="section">
<h3>🏢 Thông tin Customer</h3>
<div class="section-note">Thông tin khách hàng dùng chung cho Service, Monitoring, Alert và các công cụ IT.</div>
<div class="grid" style="grid-template-columns:1fr 2fr;gap:14px">
<div class="field"><label>Code *</label><input class="input" name="code" value="{{ old('code',$customer->code) }}" required></div>
<div class="field"><label>Customer Name *</label><input class="input" name="name" value="{{ old('name',$customer->name) }}" required></div>
</div>
<div class="grid" style="grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
<div class="field"><label>Contact Name</label><input class="input" name="contact_name" value="{{ old('contact_name',$customer->contact_name) }}"></div>
<div class="field"><label>Email</label><input class="input" type="email" name="email" value="{{ old('email',$customer->email) }}"></div>
</div>
<div class="grid" style="grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
<div class="field"><label>Phone</label><input class="input" name="phone" value="{{ old('phone',$customer->phone) }}"></div>
<div class="field" style="padding-top:28px"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$customer->exists?$customer->is_active:true))> Active</label></div>
</div>
</div>

<div class="section">
<h3>👨‍💻 Đầu mối vận hành IT</h3>
<div class="section-note">Customer được quản lý theo đúng nhân sự IT phụ trách. Quyền xem, sửa, xóa Customer và dữ liệu PC Audit liên quan sẽ theo Đầu mối vận hành IT.</div>
@if(auth()->user()->isSuperAdmin())
<div class="field">
<label>Nhân sự vận hành IT *</label>
<select class="input" name="responsible_it_id" required>
<option value="">-- Chọn nhân sự vận hành IT --</option>
@foreach($itUsers as $person)
<option value="{{ $person->id }}" @selected((int) old('responsible_it_id', $customer->responsible_it_id) === (int) $person->id)>{{ $person->name }}{{ $person->employee_code ? ' · '.$person->employee_code : '' }}{{ $person->group ? ' · '.$person->group->name : '' }}</option>
@endforeach
</select>
</div>
@else
@php($responsible = $customer->exists ? $customer->responsibleIt : auth()->user())
<div class="person-card">
<strong>{{ $responsible?->name ?: auth()->user()->name }}</strong>
<small>{{ $responsible?->email ?: auth()->user()->email }}</small>
<span class="badge">Tự động theo tài khoản đăng nhập</span>
</div>
@endif
</div>

<div class="section">
<h3>💼 Đầu mối Sales</h3>
<div class="section-note">Chỉ nhập thông tin Sales khi Customer cần Sales nhận Alert. Bật <strong>Active</strong> để Sales tham gia luồng nhận cảnh báo.</div>
<div class="sales-grid">
<div class="field"><label>Họ tên Sales</label><input class="input" name="sales_name" value="{{ old('sales_name',$customer->sales_name) }}" placeholder="Nguyễn Văn A"></div>
<div class="field"><label>Email Sales</label><input class="input" type="email" name="sales_email" value="{{ old('sales_email',$customer->sales_email) }}" placeholder="sales@example.com"></div>
</div>
<div class="toggle-row">
<label><input type="checkbox" name="sales_active" value="1" @checked(old('sales_active',$customer->exists?$customer->sales_active:false))> Active — Sales nhận Alert</label>
</div>
</div>

<div class="section">
<h3>🔔 Luồng nhận Alert</h3>
<div class="alert-note">
<strong>IT Vận hành + IT Lead + BOD + Customer</strong> sẽ nhận Alert theo cấu hình hệ thống.<br>
<strong>Sales</strong> chỉ nhận Alert khi đã nhập Họ tên/Email và bật <strong>Active</strong>.
</div>
</div>

<div class="actions"><button class="btn">💾 Lưu Customer</button><a class="btn gray" href="{{ route('admin.service_customers.index') }}">Hủy</a></div>
</form>
</div>
@endsection
