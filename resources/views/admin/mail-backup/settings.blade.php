@extends('layouts.admin')
@section('title', 'Cấu hình Email Admin')
@section('content')
<div class="card">
    <h2>Cấu hình động Email Admin</h2>
    <p class="muted">Các trường được định nghĩa trong database. Có thể thêm trường mới mà không cần sửa view/controller cho từng tham số. Bí mật được mã hóa ở tầng model và giá trị hiện tại không được hiển thị lại.</p>
    <form method="post" action="{{ route('admin.mail_backup.settings.values') }}">
        @csrf @method('PUT')
        @php($groups = $definitions->where('is_active',true)->groupBy('group'))
        @forelse($groups as $group => $items)
            <div style="margin-top:20px;padding:14px;border:1px solid #dbe3ec;border-radius:10px">
                <h3 style="margin:0 0 14px">{{ $group }}</h3>
                <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                    @foreach($items as $definition)
                        <div class="field">
                            <label>{{ $definition->label }} @if($definition->is_required)<span style="color:#b42318">*</span>@endif</label>
                            @if($definition->type === 'boolean')
                                <input type="hidden" name="settings[{{ $definition->id }}]" value="0">
                                <label style="display:flex;gap:8px;align-items:center"><input style="width:auto" type="checkbox" name="settings[{{ $definition->id }}]" value="1" @checked((string)$definition->value === '1')> Bật</label>
                            @elseif($definition->type === 'textarea')
                                <textarea class="input" rows="3" name="settings[{{ $definition->id }}]" @required($definition->is_required && !$definition->is_secret)>{{ $definition->is_secret ? '' : old('settings.'.$definition->id,$definition->value) }}</textarea>
                            @elseif($definition->type === 'select')
                                <select class="input" name="settings[{{ $definition->id }}]" @required($definition->is_required)>
                                    @foreach(($definition->options ?? []) as $optionValue => $optionLabel)<option value="{{ $optionValue }}" @selected((string)$definition->value === (string)$optionValue)>{{ $optionLabel }}</option>@endforeach
                                </select>
                            @else
                                <input class="input" type="{{ ($definition->type === 'password' || $definition->is_secret) ? 'password' : ($definition->type === 'number' ? 'number' : ($definition->type === 'url' ? 'url' : ($definition->type === 'email' ? 'email' : 'text'))) }}" name="settings[{{ $definition->id }}]" value="{{ $definition->is_secret ? '' : old('settings.'.$definition->id,$definition->value) }}" placeholder="{{ $definition->is_secret && filled($definition->value) ? 'Đã cấu hình — để trống để giữ nguyên' : '' }}" @required($definition->is_required && !$definition->is_secret)>
                            @endif
                            @if($definition->description)<div class="muted" style="margin-top:4px">{{ $definition->description }}</div>@endif
                            @if($definition->is_secret)<div class="muted">Trường bí mật; để trống sẽ giữ giá trị cũ.</div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="info">Chưa có định nghĩa cấu hình. Hãy thêm trường bên dưới.</div>
        @endforelse
        @if($definitions->where('is_active',true)->count())<button class="btn" type="submit" style="margin-top:18px">Lưu cấu hình Email Admin</button>@endif
    </form>
</div>
<div class="card" style="margin-top:18px">
    <h2>Thêm thông số cấu hình</h2>
    <p class="muted">Key là mã cấu hình ổn định dùng trong code; Label, nhóm, kiểu dữ liệu, options và mô tả được quản lý tại đây. Không dùng trường này để thực thi code hay tạo đường dẫn file tùy ý.</p>
    <form method="post" action="{{ route('admin.mail_backup.settings.store') }}">
        @csrf
        <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
            <div class="field"><label>Key</label><input class="input" name="key" required pattern="[a-z][a-z0-9_.-]{2,119}" placeholder="email_admin.reply_to"></div>
            <div class="field"><label>Nhãn hiển thị</label><input class="input" name="label" required maxlength="160" placeholder="Reply-To Email"></div>
            <div class="field"><label>Nhóm</label><input class="input" name="group" required value="Email Admin" maxlength="80"></div>
            <div class="field"><label>Kiểu dữ liệu</label><select class="input" name="type" required>@foreach(['text'=>'Text','email'=>'Email','url'=>'URL','number'=>'Number','boolean'=>'Boolean','password'=>'Password/Secret','textarea'=>'Textarea','select'=>'Dropdown'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label>Giá trị mặc định</label><input class="input" name="value" maxlength="10000"></div>
            <div class="field"><label>Thứ tự</label><input class="input" name="sort_order" type="number" min="0" value="100"></div>
        </div>
        <div class="field"><label>Mô tả</label><textarea class="input" name="description" rows="2" maxlength="2000"></textarea></div>
        <div class="field"><label>Dropdown options (JSON object, tùy chọn)</label><textarea class="input" name="options_json" rows="2" placeholder='{"all":"All Mail","inbox":"Inbox"}'></textarea></div>
        <div class="actions"><label style="display:flex;gap:8px;align-items:center"><input style="width:auto" type="checkbox" name="is_required" value="1"> Bắt buộc</label><label style="display:flex;gap:8px;align-items:center"><input style="width:auto" type="checkbox" name="is_secret" value="1"> Bí mật</label></div>
        <button class="btn" type="submit" style="margin-top:14px">＋ Thêm trường cấu hình</button>
    </form>
</div>
<div class="card" style="margin-top:18px">
    <h2>Quản lý định nghĩa</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Key</th><th>Label</th><th>Nhóm</th><th>Kiểu</th><th>Trạng thái</th><th>Cập nhật</th></tr></thead><tbody>
    @foreach($definitions as $definition)
        <tr><td><code>{{ $definition->key }}</code></td><td>{{ $definition->label }}<br><small class="muted">{{ $definition->description }}</small></td><td>{{ $definition->group }}</td><td>{{ $definition->type }} @if($definition->is_secret) · Secret @endif</td><td>{{ $definition->is_active ? 'Active' : 'Inactive' }}</td><td>
            <form method="post" action="{{ route('admin.mail_backup.settings.definition.update',$definition) }}" class="actions">@csrf @method('PUT')
                <input class="input" style="max-width:140px" name="label" value="{{ $definition->label }}" required>
                <input class="input" style="max-width:120px" name="group" value="{{ $definition->group }}" required>
                <input class="input" style="max-width:90px" type="number" name="sort_order" min="0" value="{{ $definition->sort_order }}" required>
                <label style="display:flex;align-items:center;gap:5px"><input style="width:auto" type="checkbox" name="is_required" value="1" @checked($definition->is_required)> Required</label>
                <label style="display:flex;align-items:center;gap:5px"><input style="width:auto" type="checkbox" name="is_active" value="1" @checked($definition->is_active)> Active</label>
                <input type="hidden" name="description" value="{{ $definition->description }}">
                <button class="btn gray" type="submit">Lưu</button>
            </form>
        </td></tr>
    @endforeach
    </tbody></table></div>
</div>
@endsection
