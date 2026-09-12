@extends('layouts.admin')
@section('title', isset($policy) ? 'Edit Alert Policy' : 'Add Alert Policy')
@section('content')
<div class="card form">
    <form method="post" action="{{ isset($policy) ? route('admin.service_alert_policies.update', $policy) : route('admin.service_alert_policies.store') }}">
        @csrf
        @if(isset($policy)) @method('PUT') @endif
        <div class="field">
            <label>Service Type *</label>
            <select class="input" name="service_type_id" required>
                <option value="">Select Service Type</option>
                @foreach($serviceTypes as $type)
                    <option value="{{ $type->id }}" @selected((string)old('service_type_id', $selectedServiceTypeId ?? '') === (string)$type->id)>{{ $type->name }} ({{ $type->code }})</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Policy Name *</label>
            <input class="input" name="name" value="{{ old('name', $policy->name ?? '') }}" maxlength="150" required>
        </div>
        <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:12px">
            <div class="field"><label>Alert 1 (%) *</label><input class="input" type="number" name="alert_1_percent" min="0.01" max="99.99" step="0.01" value="{{ old('alert_1_percent', $policy->alert_1_percent ?? 20) }}" required></div>
            <div class="field"><label>Alert 2 (%) *</label><input class="input" type="number" name="alert_2_percent" min="0.01" max="99.99" step="0.01" value="{{ old('alert_2_percent', $policy->alert_2_percent ?? 10) }}" required></div>
            <div class="field"><label>Alert 3 (%) *</label><input class="input" type="number" name="alert_3_percent" min="0.01" max="99.99" step="0.01" value="{{ old('alert_3_percent', $policy->alert_3_percent ?? 5) }}" required></div>
        </div>
        <div class="field">
            <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $policy->is_active ?? true))> Active</label>
        </div>
        <div class="info">Các mốc được tính theo % thời gian còn lại của Service. Alert 1 nên lớn hơn Alert 2 và Alert 2 nên lớn hơn Alert 3.</div>
        <div class="actions"><button class="btn" type="submit">Save</button><a class="btn gray" href="{{ route('admin.service_alert_policies.index') }}">Cancel</a></div>
    </form>
</div>
@endsection
