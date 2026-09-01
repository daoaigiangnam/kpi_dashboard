@extends('layouts.admin')
@section('title', $department->exists ? 'Edit Department' : 'New Department')
@section('content')
<div class="card form">
<form method="post" action="{{ $department->exists ? route('admin.departments.update',$department) : route('admin.departments.store') }}">
 @csrf @if($department->exists) @method('PUT') @endif
 <h3 style="margin-top:0">Department Information</h3>
 <div class="field"><label>Code *</label><input class="input" name="code" value="{{ old('code',$department->code) }}" required placeholder="DEPT-001"></div>
 <div class="field"><label>Department Name *</label><input class="input" name="name" value="{{ old('name',$department->name) }}" required placeholder="IT Department"></div>
 <div class="field"><label>Description</label><input class="input" name="description" value="{{ old('description',$department->description) }}" maxlength="255"></div>
 <button class="btn" type="submit">Save Department</button> <a class="btn gray" href="{{ route('admin.departments.index') }}">Cancel</a>
</form></div>
@endsection
