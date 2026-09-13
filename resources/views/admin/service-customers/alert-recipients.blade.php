@extends('layouts.admin')
@section('title','Customer Alert Recipients')
@section('content')
<div class="card form">
    <div style="margin-bottom:18px">
        <strong>Alert Recipients — {{ $serviceCustomer->name }}</strong>
        <div class="muted">Khai báo người nhận Alert riêng cho Customer này ở từng cấp. Thời gian Escalation vẫn lấy từ Alert Email Settings.</div>
    </div>

    <form method="post" action="{{ route('admin.service_customers.alert-recipients.update', $serviceCustomer) }}">
        @csrf @method('PUT')
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th style="width:90px">Level</th><th>Người nhận</th><th>Email</th><th>Phone</th><th style="width:90px">Active</th></tr></thead>
                <tbody>
                @foreach([1 => 'Level 1 — Operations Staff / NV vận hành', 2 => 'Level 2 — IT Lead', 3 => 'Level 3 — BOD Outsourcing', 4 => 'Level 4 — Customer'] as $level => $label)
                    @php($r = $recipients->get($level))
                    <tr>
                        <td><strong>Alert {{ $level }}</strong><div class="muted" style="font-size:12px">{{ $label }}</div></td>
                        <td><input class="input" name="recipients[{{ $level }}][name]" value="{{ old("recipients.$level.name", $r?->recipient_name) }}" placeholder="Họ tên"></td>
                        <td><input class="input" type="email" name="recipients[{{ $level }}][email]" value="{{ old("recipients.$level.email", $r?->recipient_email) }}" placeholder="email@example.com"></td>
                        <td><input class="input" name="recipients[{{ $level }}][phone]" value="{{ old("recipients.$level.phone", $r?->recipient_phone) }}" placeholder="Số điện thoại"></td>
                        <td><label><input type="checkbox" name="recipients[{{ $level }}][is_active]" value="1" @checked(old("recipients.$level.is_active", $r?->is_active ?? true))> Active</label></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="actions" style="margin-top:18px">
            <button class="btn">Save Recipients</button>
            <a class="btn gray" href="{{ route('admin.service_customers.index') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
