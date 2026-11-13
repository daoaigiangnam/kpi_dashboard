<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cập nhật tình trạng tài sản</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f3f7f5;color:#163328}
.wrap{max-width:800px;margin:24px auto;padding:24px}
.card{background:#fff;border:1px solid #dfe9e3;border-radius:14px;padding:24px}
.field{margin:18px 0}.field label{display:block;font-weight:600;margin-bottom:8px}
.field select{width:100%;padding:12px;border:1px solid #ccd9d0;border-radius:8px;background:#fff}
.btn{display:inline-block;padding:10px 16px;border:0;border-radius:7px;background:#0f766e;color:#fff;text-decoration:none;cursor:pointer}
.muted{color:#718178}
</style></head>
<body><main class="wrap"><section class="card">
<h1>Cập nhật tình trạng tài sản</h1>
<p class="muted">{{ $asset->computer_name }} · {{ $asset->serial_number }}</p>
@if($errors->any())<p style="color:#b91c1c">{{ $errors->first() }}</p>@endif
<form method="POST" action="{{ route('customer.assets.update', $asset->asset_lookup_token) }}">
@csrf @method('PUT')
<div class="field"><label for="asset_condition">Tình trạng tài sản</label>
<select id="asset_condition" name="asset_condition" required>
@foreach(['Đang sử dụng','Dự phòng','Hỏng','Đang sửa chữa','Thanh lý','Mất'] as $condition)
<option value="{{ $condition }}" @selected(old('asset_condition', $asset->asset_condition ?: 'Đang sử dụng') === $condition)>{{ $condition }}</option>
@endforeach
</select></div>
<button class="btn" type="submit">Lưu</button>
<a class="btn" href="{{ route('customer.assets.show', $asset->asset_lookup_token) }}">Hủy</a>
</form></section></main></body></html>