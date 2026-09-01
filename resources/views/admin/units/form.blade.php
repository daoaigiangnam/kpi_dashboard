@extends('layouts.admin')
@section('title', $unit->exists ? 'Edit Unit' : 'New Unit')
@section('content')
<div class="card form">
<form method="post" action="{{ $unit->exists ? route('admin.units.update',$unit) : route('admin.units.store') }}">
 @csrf @if($unit->exists) @method('PUT') @endif
 <div class="field"><label>Code *</label><input class="input" name="code" value="{{ old('code',$unit->code) }}" required></div>
 <div class="field"><label>Unit Name *</label><input class="input" name="name" value="{{ old('name',$unit->name) }}" required></div>
 <div class="field"><label>Description</label><input class="input" name="description" value="{{ old('description',$unit->description) }}"></div>
 <div class="field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$unit->is_active))> Active</label></div>
 <button class="btn" type="submit">Save Unit</button> <a class="btn gray" href="{{ route('admin.units.index') }}">Cancel</a>
</form></div>
@endsection
