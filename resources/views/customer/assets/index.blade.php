<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tài sản của tôi</title>
<style>
:root{--green:#123b2a;--teal:#0f766e;--border:#dfe9e3;--muted:#718178}*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f3f7f5;color:#163328}.wrap{max-width:1500px;margin:auto;padding:22px}.hero{background:linear-gradient(135deg,#123b2a,#0f766e);color:#fff;padding:24px;border-radius:15px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.hero h1{margin:0 0 8px;font-size:26px}.card{background:#fff;border:1px solid var(--border);border-radius:13px;padding:18px;margin-bottom:16px}.toolbar{display:grid;grid-template-columns:minmax(220px,1fr) repeat(3,minmax(150px,210px)) auto auto;gap:10px;align-items:end}.field label{display:block;font-size:12px;font-weight:700;margin-bottom:6px;color:#4b6257}.field input,.field select{width:100%;height:40px;padding:8px 10px;border:1px solid #ccd9d0;border-radius:8px;background:#fff}.btn{display:inline-flex;align-items:center;justify-content:center;padding:9px 12px;border:0;border-radius:8px;background:var(--teal);color:white;text-decoration:none;cursor:pointer;font-size:13px;white-space:nowrap}.btn.secondary{background:white;border:1px solid #cbd5e1;color:#334155}.btn.danger{background:#b91c1c}.btn.small{padding:7px 9px}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:10px}table{width:100%;min-width:1150px;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:12px;border-bottom:1px solid #e6ede9;vertical-align:middle}th{background:#f5f9f7;color:#4a6257;font-size:11px;text-transform:uppercase;white-space:nowrap}tbody tr:hover{background:#f8fffd}.muted{color:var(--muted);font-size:12px}.actions{display:flex;gap:6px;flex-wrap:wrap}.alert{padding:12px 14px;border-radius:9px;margin-bottom:12px;background:#ecfdf5;color:#065f46}.alert.error{background:#fef2f2;color:#991b1b}.status{display:inline-block;padding:4px 8px;border-radius:99px;background:#fef3c7;color:#92400e;font-weight:700;font-size:11px}.pagination{margin-top:16px}@media(max-width:1050px){.toolbar{grid-template-columns:repeat(2,minmax(0,1fr))}.toolbar .wide{grid-column:1/-1}}@media(max-width:600px){.wrap{padding:10px}.hero{padding:18px}.hero h1{font-size:22px}.card{padding:12px}.toolbar{grid-template-columns:1fr}.toolbar .wide{grid-column:auto}.btn{min-height:38px}}
</style></head><body><main class="wrap">
<header class="hero"><div><h1>Danh sách tài sản của tôi</h1><div>{{ auth()->user()->customer?->name }}</div><p style="margin-bottom:0">Chỉ hiển thị tài sản thuộc khách hàng và chi nhánh được cấp quyền.</p></div><div class="actions"><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn" type="submit">Đăng xuất</button></form></div></header>
@if(session('success'))<div class="alert">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<section class="card"><form method="GET" action="{{ route('customer.assets.index') }}" class="toolbar">
<div class="field wide"><label>Tìm kiếm</label><input name="q" value="{{ request('q') }}" placeholder="Computer Name, Asset Tag, Serial, người sử dụng, hãng hoặc model"></div>
<div class="field"><label>Chi nhánh</label><select name="branch_id"><option value="">Tất cả chi nhánh được cấp quyền</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)request('branch_id')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
<div class="field"><label>Trạng thái Audit</label><select name="status"><option value="">Tất cả trạng thái</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div>
<div class="field"><label>Hãng sản xuất</label><select name="manufacturer"><option value="">Tất cả hãng</option>@foreach($manufacturers as $manufacturer)<option value="{{ $manufacturer }}" @selected(request('manufacturer')===$manufacturer)>{{ $manufacturer }}</option>@endforeach</select></div>
<button class="btn" type="submit">Lọc / Tìm kiếm</button><a class="btn secondary" href="{{ route('customer.assets.index') }}">Xóa lọc</a>
</form><div class="actions" style="margin-top:14px">
@if(auth()->user()->hasPermission('customer.assets.create'))<a class="btn" href="{{ route('customer.assets.create') }}">+ Tạo tài sản</a>@endif
@if(auth()->user()->hasPermission('customer.assets.export'))
<form id="asset-export-form" method="GET" action="{{ route('customer.assets.export') }}" class="actions" style="margin:0;align-items:center">
@foreach(request()->query() as $key=>$value) @if(!is_array($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
<label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" id="export-all-filtered" name="all_filtered" value="1"> Xuất toàn bộ kết quả theo bộ lọc</label>
<button class="btn secondary" type="submit">Xuất Excel (.xlsx)</button>
</form>
@else
<span class="muted" title="Liên hệ quản trị viên để được cấp quyền xuất dữ liệu">Xuất Excel: chưa được cấp quyền</span>
@endif
</div></section>
<section class="card"><div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px"><strong>Danh sách tài sản</strong><span class="muted">{{ number_format($assets->total()) }} bản ghi</span></div>
<div class="table-wrap"><table><thead><tr><th><input type="checkbox" id="select-visible-assets" aria-label="Chọn tất cả trên trang"></th><th>Tài sản</th><th>Asset Tag</th><th>Serial</th><th>Chi nhánh</th><th>Người sử dụng</th><th>Trạng thái</th><th>Ngày kiểm kê</th><th>Thao tác</th></tr></thead><tbody>
@forelse($assets as $asset)<tr><td><input type="checkbox" class="asset-select" value="{{ $asset->id }}" aria-label="Chọn tài sản"></td><td><strong>{{ $asset->computer_name ?: 'Chưa đặt tên' }}</strong><div class="muted">{{ $asset->manufacturer }} {{ $asset->model }}</div></td><td>{{ $asset->asset_tag ?: '—' }}</td><td>{{ $asset->serial_number ?: '—' }}</td><td>{{ $asset->auditCode?->branch?->name ?: '—' }}</td><td>{{ $asset->employee_name ?: '—' }}</td><td><span class="status">{{ $asset->audit_status ?: 'REVIEW' }}</span></td><td>{{ $asset->collected_at?->format('d/m/Y H:i') ?: '—' }}</td><td><div class="actions">
<a class="btn small" href="{{ route('customer.assets.show',$asset->asset_lookup_token) }}">Chi tiết</a>
@if(auth()->user()->hasPermission('customer.assets.qr'))<a class="btn small secondary" href="{{ route('customer.assets.qr',$asset->asset_lookup_token) }}">QR</a>@endif
@if(auth()->user()->hasPermission('customer.assets.edit'))<a class="btn small secondary" href="{{ route('customer.assets.edit',$asset->asset_lookup_token) }}">Sửa</a>@endif
@if(auth()->user()->hasPermission('customer.assets.delete'))<form method="POST" action="{{ route('customer.assets.destroy',$asset->asset_lookup_token) }}" onsubmit="return confirm('Xác nhận xóa tài sản này?')">@csrf @method('DELETE')<button class="btn small danger" type="submit">Xóa</button></form>@endif
</div></td></tr>
@empty<tr><td colspan="9" style="text-align:center;padding:30px">Không có tài sản phù hợp bộ lọc.</td></tr>@endforelse
</tbody></table></div><div class="pagination">{{ $assets->links() }}</div></section>
</main><script>
(function(){
 const selectVisible=document.getElementById('select-visible-assets');
 const allFiltered=document.getElementById('export-all-filtered');
 const exportForm=document.getElementById('asset-export-form');
 if(selectVisible) selectVisible.addEventListener('change',()=>document.querySelectorAll('.asset-select').forEach(cb=>cb.checked=selectVisible.checked));
 if(exportForm) exportForm.addEventListener('submit',function(e){
   exportForm.querySelectorAll('input[name="ids[]"]').forEach(el=>el.remove());
   const all=allFiltered && allFiltered.checked;
   const selected=Array.from(document.querySelectorAll('.asset-select:checked'));
   if(!all && selected.length===0){e.preventDefault();alert('Hãy chọn ít nhất một tài sản hoặc chọn Xuất toàn bộ kết quả theo bộ lọc.');return;}
   if(!all) selected.forEach(cb=>{const input=document.createElement('input');input.type='hidden';input.name='ids[]';input.value=cb.value;exportForm.appendChild(input);});
   const button=exportForm.querySelector('button[type=submit]'); if(button){button.disabled=true;button.textContent='Đang tạo file Excel…';}
 });
})();
</script></body></html>