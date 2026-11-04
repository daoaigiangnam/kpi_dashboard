@extends('layouts.admin')
@section('title', $provider->exists ? 'Edit Provider' : 'New Provider')
@section('content')
<div class="card">
    <h2>{{ $provider->exists ? 'Edit Provider' : 'New Provider' }}</h2>
    <form method="post" action="{{ $provider->exists ? route('admin.service_providers.update',$provider) : route('admin.service_providers.store') }}">
        @csrf
        @if($provider->exists) @method('PUT') @endif
        <div class="grid">
            <label>Code *<input name="code" value="{{ old('code',$provider->code) }}" required></label>
            <label>Name *<input name="name" value="{{ old('name',$provider->name) }}" required></label>
            <label>Website<input type="url" name="website" value="{{ old('website',$provider->website) }}"></label>
            <label>Support Contact<input name="support_contact" value="{{ old('support_contact',$provider->support_contact) }}"></label>
            <label>Status
                <select name="is_active">
                    <option value="1" @selected(old('is_active',$provider->exists ? $provider->is_active : true))>Active</option>
                    <option value="0" @selected((string)old('is_active',$provider->exists ? $provider->is_active : true)==='0')>Inactive</option>
                </select>
            </label>
        </div>
        @if($errors->any())<div class="alert alert-error" style="margin-top:12px">{{ $errors->first() }}</div>@endif
        <div class="actions" style="margin-top:16px">
            <button class="btn" type="submit">Save</button>
            <a class="btn" href="{{ route('admin.service_providers.index') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
