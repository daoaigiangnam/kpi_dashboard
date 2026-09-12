@extends('layouts.admin')
@section('title', $customer->exists ? 'Edit Customer' : 'Add Customer')
@section('content')
<div class="card form"><form method="post" action="{{ $customer->exists ? route('admin.service_customers.update',$customer) : route('admin.service_customers.store') }}">
@csrf @if($customer->exists) @method('PUT') @endif
<div class="field"><label>Code *</label><input class="input" name="code" value="{{ old('code',$customer->code) }}" required></div>
<div class="field"><label>Customer Name *</label><input class="input" name="name" value="{{ old('name',$customer->name) }}" required></div>
<div class="field"><label>Contact Name</label><input class="input" name="contact_name" value="{{ old('contact_name',$customer->contact_name) }}"></div>
<div class="field"><label>Email</label><input class="input" type="email" name="email" value="{{ old('email',$customer->email) }}"></div>
<div class="field"><label>Phone</label><input class="input" name="phone" value="{{ old('phone',$customer->phone) }}"></div>
<div class="field"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$customer->exists?$customer->is_active:true))> Active</label></div>
<div class="actions"><button class="btn">Save</button><a class="btn gray" href="{{ route('admin.service_customers.index') }}">Cancel</a></div>
</form></div>
@endsection
