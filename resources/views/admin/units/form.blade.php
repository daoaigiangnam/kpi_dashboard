@extends('layouts.admin')
@section('title', $unit->exists ? 'Edit Unit' : 'New Unit')
@section('content')
<div class="card form">
<form method="post" action="{{ $unit->exists ? route('admin.units.update',$unit) : route('admin.units.store') }}">
 @csrf @if($unit->exists) @method('PUT') @endif
 <h3 style="margin-top:0">Organization Unit Information</h3>
 <div class="grid" style="grid-template-columns:1fr 1fr">
  <div class="field"><label>Unit Code *</label><input class="input" name="code" value="{{ old('code',$unit->code) }}" required placeholder="UNIT-001"></div>
  <div class="field"><label>Unit Name *</label><input class="input" name="name" value="{{ old('name',$unit->name) }}" required placeholder="Mstar Corp"></div>
  <div class="field" style="grid-column:1 / -1"><label>Address *</label><input class="input" name="address" value="{{ old('address',$unit->address) }}" required placeholder="75 Hoàng Văn Thụ, ..."></div>
  <div class="field"><label>Phone *</label><input class="input" name="phone" value="{{ old('phone',$unit->phone) }}" required placeholder="028..."></div>
  <div class="field"><label>Tax Code (MST) *</label><input class="input" name="tax_code" value="{{ old('tax_code',$unit->tax_code) }}" required placeholder="0123456789"></div>
  <div class="field" style="grid-column:1 / -1"><label>Description</label><textarea class="input" name="description" rows="3" maxlength="255">{{ old('description',$unit->description) }}</textarea></div>
 </div>
 <button class="btn" type="submit">Save Unit</button> <a class="btn gray" href="{{ route('admin.units.index') }}">Cancel</a>
</form></div>
@endsection
