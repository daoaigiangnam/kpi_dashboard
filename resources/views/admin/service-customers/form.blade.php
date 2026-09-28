@extends('layouts.admin')
@section('title', $customer->exists ? 'Edit Customer' : 'Add Customer')
@section('content')
<style>
.customer-form{max-width:980px}.customer-form .section{background:#fff;border:1px solid #e1e9e4;border-radius:12px;padding:18px 20px;margin-bottom:16px}.customer-form .section h3{margin:0 0 6px}.customer-form .section-note{color:#66736b;font-size:13px;margin-bottom:14px}.customer-groups{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.customer-group{border:1px solid #d8e3dc;border-radius:8px;padding:10px;background:#fbfdfc}.customer-group label{display:flex;gap:8px;align-items:center}.customer-group small{display:block;color:#66736b;margin:4px 0 0 24px}@media(max-width:700px){.customer-groups{grid-template-columns:1fr}}
</style>
<div class="customer-form">
<form method="post" action="{{ $customer->exists ? route('admin.service_customers.update',$customer) : route('admin.service_customers.store') }}">
@csrf @if($customer->exists) @method('PUT') @endif

<div class="section">
<h3>Thông tin Customer</h3>
<div class="section-note">Thông tin khách hàng dùng chung cho Service, Monitoring, Alert và các công cụ IT.</div>
<div class="grid" style="grid-template-columns:1fr 2fr;gap:14px">
<div class="field"><label>Code *</label><input class="input" name="code" value="{{ old('code',$customer->code) }}" required></div>
<div class="field"><label>Customer Name *</label><input class="input" name="name" value="{{ old('name',$customer->name) }}" required></div>
</div>
<div class="grid" style="grid-template-columns:1fr 1fr;gap:14px">
<div class="field"><label>Contact Name</label><input class="input" name="contact_name" value="{{ old('contact_name',$customer->contact_name) }}"></div>
<div class="field"><label>Email</label><input class="input" type="email" name="email" value="{{ old('email',$customer->email) }}"></div>
</div>
<div class="grid" style="grid-template-columns:1fr 1fr;gap:14px">
<div class="field"><label>Phone</label><input class="input" name="phone" value="{{ old('phone',$customer->phone) }}"></div>
<div class="field" style="padding-top:28px"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$customer->exists?$customer->is_active:true))> Active</label></div>
</div>
</div>

@if(auth()->user()->hasPermission('service_customers.access'))
<div class="section">
<h3>👥 Nhóm IT được phép quản lý Customer</h3>
<div class="section-note">Chỉ User thuộc các Group được chọn mới nhìn thấy Customer này trong danh mục Customer và được quản lý các Service của Customer. Super Admin luôn nhìn thấy toàn bộ.</div>
<div class="customer-groups">
@foreach($groups as $group)
@php($selected = old('group_ids', $customer->exists ? $customer->groups->pluck('id')->all() : []))
<div class="customer-group">
<label><input type="checkbox" name="group_ids[]" value="{{ $group->id }}" @checked(in_array($group->id,$selected))> <strong>{{ $group->name }}</strong></label>
<small>{{ $group->users()->count() }} user</small>
</div>
@endforeach
</div>
@if($groups->isEmpty())<div class="muted">Chưa có User Group để phân quyền.</div>@endif
</div>
@else
<div class="section">
<h3>👥 Nhóm quản lý</h3>
<div class="section-note">Customer sẽ được tự động gán cho Group của bạn khi tạo mới. Chỉ người có quyền <strong>Manage Customer Access</strong> mới thay đổi được danh sách Group quản lý.</div>
</div>
@endif

@php($r = $customer->exists ? $customer->alertRecipients->firstWhere('level', 1) : null)
<div class="section">
<h3>📧 Customer Alert Contact</h3>
<div class="section-note">Đầu mối Operations Staff / NV vận hành của Customer.</div>
<div class="table-wrap">
<table class="table">
<thead><tr><th>Alert Level</th><th>Người nhận</th><th>Email *</th><th>Phone</th><th>Active</th></tr></thead>
<tbody><tr>
<td><strong>Alert 1</strong><div class="muted" style="font-size:12px">Operations Staff / NV vận hành</div></td>
<td><input class="input" name="alert_recipient[name]" value="{{ old('alert_recipient.name', $r?->recipient_name) }}" placeholder="Họ tên"></td>
<td><input class="input" type="email" name="alert_recipient[email]" value="{{ old('alert_recipient.email', $r?->recipient_email) }}" placeholder="email@example.com"></td>
<td><input class="input" name="alert_recipient[phone]" value="{{ old('alert_recipient.phone', $r?->recipient_phone) }}" placeholder="Số điện thoại"></td>
<td><label><input type="checkbox" name="alert_recipient[is_active]" value="1" @checked(old('alert_recipient.is_active', $r?->is_active ?? true))> Active</label></td>
</tr></tbody>
</table>
</div>
</div>

<div class="actions"><button class="btn">Save</button> <a class="btn gray" href="{{ route('admin.service_customers.index') }}">Cancel</a></div>
</form>
</div>
@endsection
