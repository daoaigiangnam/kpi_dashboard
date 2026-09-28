@extends('layouts.admin')
@section('title', $customer->exists ? 'Edit Customer' : 'Add Customer')
@section('content')
<style>
.customer-form{max-width:980px}.customer-form .section{background:#fff;border:1px solid #e1e9e4;border-radius:12px;padding:18px 20px;margin-bottom:16px}.customer-form .section h3{margin:0 0 6px}.customer-form .section-note{color:#66736b;font-size:13px;margin-bottom:14px}.person-card{border:1px solid #d8e3dc;border-radius:9px;padding:12px 14px;background:#fbfdfc}.person-card strong{display:block}.person-card small{display:block;color:#66736b;margin-top:4px}.person-card .badge{display:inline-block;margin-top:7px;padding:3px 8px;border-radius:999px;background:#e8f5ef;color:#08745c;font-size:12px;font-weight:600}.customer-groups{display:none}
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

<div class="section">
<h3>👨‍💻 Đầu mối vận hành IT</h3>
<div class="section-note">Customer được quản lý theo Đầu mối vận hành IT. Quyền xem Customer, Service và PC Audit sẽ đi theo IT phụ trách/nhóm IT của nhân sự này.</div>
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
<div class="section-note">Nhân sự Sales phụ trách Customer. Khi phát sinh Alert, Sales sẽ nhận thông báo cùng IT Vận hành, IT Lead, BOD và Customer.</div>
<div class="field">
<label>Nhân sự Sales</label>
<select class="input" name="sales_contact_id">
<option value="">-- Chưa chọn --</option>
@foreach($salesUsers as $person)
<option value="{{ $person->id }}" @selected((int) old('sales_contact_id', $customer->sales_contact_id) === (int) $person->id)>{{ $person->name }}{{ $person->employee_code ? ' · '.$person->employee_code : '' }}{{ $person->group ? ' · '.$person->group->name : '' }}</option>
@endforeach
</select>
</div>
</div>

@php($r = $customer->exists ? $customer->alertRecipients->firstWhere('level', 1) : null)
<div class="section">
<h3>📧 Customer Alert Contact</h3>
<div class="section-note">Đầu mối nhận email cảnh báo phía Customer. Email này sẽ nhận Alert cùng với IT Vận hành, IT Lead, Sales và BOD.</div>
<div class="table-wrap">
<table class="table">
<thead><tr><th>Người nhận</th><th>Email *</th><th>Phone</th><th>Active</th></tr></thead>
<tbody><tr>
<td><input class="input" name="alert_recipient[name]" value="{{ old('alert_recipient.name', $r?->recipient_name) }}" placeholder="Họ tên"></td>
<td><input class="input" type="email" name="alert_recipient[email]" value="{{ old('alert_recipient.email', $r?->recipient_email) }}" placeholder="email@example.com"></td>
<td><input class="input" name="alert_recipient[phone]" value="{{ old('alert_recipient.phone', $r?->recipient_phone) }}" placeholder="Số điện thoại"></td>
<td><label><input type="checkbox" name="alert_recipient[is_active]" value="1" @checked(old('alert_recipient.is_active', $r?->is_active ?? true))> Active</label></td>
</tr></tbody>
</table>
</div>
</div>

<div class="section" style="background:#f7fbfa">
<h3>🔔 Luồng nhận Alert</h3>
<div class="section-note" style="margin-bottom:0"><strong>IT Vận hành → IT Lead → Sales → BOD → Customer</strong> sẽ cùng nhận thông báo khi hệ thống phát sinh Alert.</div>
</div>

<div class="actions"><button class="btn">Save</button> <a class="btn gray" href="{{ route('admin.service_customers.index') }}">Cancel</a></div>
</form>
</div>
@endsection
