@extends('layouts.admin')
@section('title', $department->exists ? 'Edit Department' : 'New Department')
@section('content')
<div class="card form">
<form method="post" action="{{ $department->exists ? route('admin.departments.update',$department) : route('admin.departments.store') }}">
 @csrf @if($department->exists) @method('PUT') @endif
 <div class="field"><label>Code *</label><input class="input" name="code" value="{{ old('code',$department->code) }}" required></div>
 <div class="field"><label>Department Name *</label><input class="input" name="name" value="{{ old('name',$department->name) }}" required></div>
 <div class="field"><label>Description</label><input class="input" name="description" value="{{ old('description',$department->description) }}"></div>
 <div class="field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$department->is_active))> Active</label></div>
 <button class="btn" type="submit">Save Department</button> <a class="btn gray" href="{{ route('admin.departments.index') }}">Cancel</a>
</form></div>
@endsection
