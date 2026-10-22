<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $asset->exists ? 'Sửa tài sản' : 'Tạo tài sản' }}</title>
<style>body{font-family:Arial,sans-serif;background:#f3f7f5;color:#163328;margin:0}.wrap{max-width:800px;margin:auto;padding:18px}.card{background:#fff;border:1px solid #dfe9e3;border-radius:14px;padding:22px}.field{margin:14px 0}.field label{display:block;font-weight:700;font-size:13px;margin-bottom:6px}.field input,.field select{box-sizing:border-box;width:100%;padding:11px;border:1px solid #ccd9d0;border-radius:8px}.btn{display:inline-block;padding:10px 14px;background:#0f766e;color:#fff;border:0;border-radius:8px;text-decoration:none;cursor:pointer}.muted{color:#718178;font-size:13px}.error{color:#b91c1c}</style></head>
<body><main class="wrap"><section class="card">
<h1>{{ $asset->exists ? 'Sửa thông tin tài sản' : 'Tạo tài sản' }}</h1>
@if($errors->any())<div class="error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ $asset->exists ? route('customer.assets.update',$asset->asset_lookup_token) : route('customer.assets.store') }}">
@csrf @if($asset->exists) @method('PUT') @endif
<div class="field"><label>Chi nhánh / Audit Code</label><select name="pc_audit_code_id" required><option value="">-- Chọn Audit Code thuộc phạm vi của bạn --</option>@foreach($branches as $branch)@foreach($branch->pcAuditCodes as $code)<option value="{{ $code->id }}" @selected((string)old('pc_audit_code_id',$asset->pc_audit_code_id)===(string)$code->id)>{{ $branch->name }} — {{ $code->code }}</option>@endforeach @endforeach</select><div class="muted">Chỉ hiển thị Audit Code thuộc khách hàng và chi nhánh đã được cấp quyền.</div></div>
@foreach(['computer_name'=>'Tên máy / Computer Name','asset_tag'=>'Mã tài sản / Asset Tag','serial_number'=>'Serial Number','manufacturer'=>'Hãng sản xuất','model'=>'Model','employee_name'=>'Người sử dụng','employee_mobile'=>'Số di động người sử dụng','employee_email'=>'Email người sử dụng','department'=>'Phòng ban'] as $name=>$label)
<div class="field"><label>{{ $label }}</label><input name="{{ $name }}" value="{{ old($name,$asset->{$name}) }}" @if($name==='computer_name') required @endif maxlength="255"></div>
@endforeach
<button class="btn" type="submit">Lưu</button> <a class="btn" href="{{ route('customer.assets.index') }}">Hủy</a>
</form></section></main></body></html>