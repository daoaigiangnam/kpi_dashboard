@extends('layouts.admin')
@section('title', $customer->exists ? 'Edit Customer' : 'Add Customer')
@section('content')
<div class="card form">
<form method="post" action="{{ $customer->exists ? route('admin.service_customers.update',$customer) : route('admin.service_customers.store') }}">
@csrf @if($customer->exists) @method('PUT') @endif
<div class="field"><label>Code *</label><input class="input" name="code" value="{{ old('code',$customer->code) }}" required></div>
<div class="field"><label>Customer Name *</label><input class="input" name="name" value="{{ old('name',$customer->name) }}" required></div>
<div class="field"><label>Contact Name</label><input class="input" name="contact_name" value="{{ old('contact_name',$customer->contact_name) }}"></div>
<div class="field"><label>Email</label><input class="input" type="email" name="email" value="{{ old('email',$customer->email) }}"></div>
<div class="field"><label>Phone</label><input class="input" name="phone" value="{{ old('phone',$customer->phone) }}"></div>
<div class="field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$customer->exists?$customer->is_active:true))> Active</label></div>

@php($alertRecipients = $customer->exists ? $customer->alertRecipients->keyBy('level') : collect())
<div style="margin-top:24px;margin-bottom:12px"><strong>Alert Recipients by Level</strong><div class="muted">Khai báo người nhận Alert riêng cho Customer này. Mỗi cấp có 1 người nhận; thời gian Escalation cấu hình tại System Settings.</div></div>
<div class="table-wrap">
<table class="table">
<thead><tr><th>Level</th><th>Người nhận</th><th>Email *</th><th>Phone</th><th>Active</th></tr></thead>
<tbody>
@foreach([1 => 'Operations Staff / NV vận hành', 2 => 'IT Lead', 3 => 'BOD Outsourcing', 4 => 'Customer'] as $level => $label)
@php($r = $alertRecipients->get($level))
<tr>
<td><strong>Alert {{ $level }}</strong><div class="muted" style="font-size:12px">{{ $label }}</div></td>
<td><input class="input" name="alert_recipients[{{ $level }}][name]" value="{{ old("alert_recipients.$level.name", $r?->recipient_name) }}" placeholder="Họ tên"></td>
<td><input class="input" type="email" name="alert_recipients[{{ $level }}][email]" value="{{ old("alert_recipients.$level.email", $r?->recipient_email) }}" placeholder="email@example.com"></td>
<td><input class="input" name="alert_recipients[{{ $level }}][phone]" value="{{ old("alert_recipients.$level.phone", $r?->recipient_phone) }}" placeholder="Số điện thoại"></td>
<td><label><input type="checkbox" name="alert_recipients[{{ $level }}][is_active]" value="1" @checked(old("alert_recipients.$level.is_active", $r?->is_active ?? false))> Active</label></td>
</tr>
@endforeach
</tbody>
</table>
</div>

<div class="actions" style="margin-top:18px"><button class="btn">Save</button><a class="btn gray" href="{{ route('admin.service_customers.index') }}">Cancel</a></div>
</form></div>
@endsection
