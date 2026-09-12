@extends('layouts.admin')
@section('title', $serviceType->exists ? 'Edit Service Type' : 'Add Service Type')
@section('content')
<div class="card form">
<form method="post" action="{{ $serviceType->exists ? route('admin.service_types.update', $serviceType) : route('admin.service_types.store') }}">
    @csrf
    @if($serviceType->exists) @method('PUT') @endif
    <div class="field"><label>Code *</label><input class="input" name="code" value="{{ old('code', $serviceType->code) }}" placeholder="DOMAIN"><div class="muted">Only letters, numbers, hyphens and underscores.</div></div>
    <div class="field"><label>Service Type *</label><input class="input" name="name" value="{{ old('name', $serviceType->name) }}" placeholder="Domain"></div>
    <div class="field"><label>Description</label><input class="input" name="description" value="{{ old('description', $serviceType->description) }}"></div>
    <div class="field">
        <label>Allowed Service Terms *</label>
        <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:9px">
            @foreach([1,3,6,9,12,24] as $months)
                <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="terms[]" value="{{ $months }}" {{ in_array($months, old('terms', $selectedTerms), true) ? 'checked' : '' }}> {{ $months }} tháng</label>
            @endforeach
        </div>
        <div class="muted" style="margin-top:7px">Chỉ các chu kỳ được chọn mới xuất hiện khi khai báo Service.</div>
    </div>
    <div class="field"><label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $serviceType->exists ? $serviceType->is_active : true) ? 'checked' : '' }}> Active</label></div>
    <div class="actions"><button class="btn" type="submit">Save</button><a class="btn gray" href="{{ route('admin.service_types.index') }}">Cancel</a></div>
</form>
</div>
@endsection
