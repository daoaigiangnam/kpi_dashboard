@extends('layouts.admin')
@section('title','Đăng ký Server')
@section('content')
<div class="top"><div><h1>Đăng ký Ubuntu Server</h1><p class="muted">Tạo Agent ID và token riêng cho máy chủ.</p></div></div>
<div class="card form"><form method="POST" action="{{ route('admin.server_monitoring.store') }}">@csrf
<div class="field"><label>Tên Server</label><input class="input" name="name" required maxlength="150" value="{{ old('name') }}" placeholder="Ubuntu Production 01"></div>
<div class="field"><label>Hostname</label><input class="input" name="hostname" maxlength="190" value="{{ old('hostname') }}"></div>
<div class="field"><label>Server Code</label><input class="input" name="server_code" required maxlength="80" pattern="[A-Za-z0-9_-]+" value="{{ old('server_code') }}" placeholder="PROD01"></div>
<button class="btn" type="submit">Tạo Agent Credentials</button> <a class="btn gray" href="{{ route('admin.server_monitoring.index') }}">Hủy</a></form></div>
@endsection