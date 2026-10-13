<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailBackupAuditLog;
use App\Models\MailBackupSettingDefinition;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MailBackupSettingController extends Controller
{
    public function index()
    {
        $definitions = MailBackupSettingDefinition::query()
            ->orderBy('group')->orderBy('sort_order')->orderBy('label')->get();

        return view('admin.mail-backup.settings', compact('definitions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'regex:/^[a-z][a-z0-9_.-]{2,119}$/', 'unique:mail_backup_setting_definitions,key'],
            'label' => ['required', 'string', 'max:160'],
            'group' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(['text','email','url','number','boolean','password','textarea','select'])],
            'value' => ['nullable', 'string', 'max:10000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'options_json' => ['nullable', 'string', 'max:10000'],
            'is_secret' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $options = null;
        if (filled($data['options_json'] ?? null)) {
            $options = json_decode($data['options_json'], true);
            if (!is_array($options) || array_is_list($options)) {
                throw ValidationException::withMessages(['options_json' => 'Options phải là JSON object dạng {"value":"Label"}.']);
            }
        }
        if ($data['type'] === 'select' && !$options) {
            throw ValidationException::withMessages(['options_json' => 'Trường select cần khai báo options.']);
        }
        $value = (string) ($data['value'] ?? '');
        $this->validateValue($data['type'], $value, $options, (bool) ($data['is_required'] ?? false));

        $definition = MailBackupSettingDefinition::create([
            'key' => $data['key'], 'label' => $data['label'], 'group' => $data['group'],
            'type' => $data['type'], 'value' => $value, 'description' => $data['description'] ?? null,
            'validation_rules' => null, 'options' => $options,
            'is_secret' => $secret,
            'is_required' => (bool) ($data['is_required'] ?? false), 'is_active' => true,
            'sort_order' => (int) ($data['sort_order'] ?? 0), 'updated_by' => auth()->id(),
        ]);

        $this->audit('SETTING_DEFINITION_CREATED', $definition, ['key' => $definition->key, 'type' => $definition->type]);
        return redirect()->route('admin.mail_backup.settings')->with('success', 'Đã thêm thông số cấu hình động.');
    }

    public function updateValues(Request $request)
    {
        $definitions = MailBackupSettingDefinition::query()->where('is_active', true)->get();
        $input = $request->input('settings', []);
        if (!is_array($input)) throw ValidationException::withMessages(['settings' => 'Dữ liệu cấu hình không hợp lệ.']);

        $values = [];
        foreach ($definitions as $definition) {
            $id = (string) $definition->id;
            $present = array_key_exists($id, $input);
            $raw = $present ? $input[$id] : null;

            if ($definition->is_secret && (!$present || trim((string) $raw) === '')) {
                continue; // Blank secret means retain current encrypted value.
            }

            if (!$present && $definition->type !== 'boolean') {
                if ($definition->is_required) throw ValidationException::withMessages(["settings.{$id}" => "Thiếu giá trị {$definition->label}."]);
                continue;
            }

            if ($definition->type === 'boolean') {
                $value = $present && in_array((string) $raw, ['1', 'true', 'on'], true) ? '1' : '0';
            } else {
                $value = is_scalar($raw) ? trim((string) $raw) : '';
                if ($definition->is_secret && $value !== '') $value = encrypt($value);
            }
            $this->validateValue($definition->type, $value, $definition->options, $definition->is_required);
            $values[$definition->id] = $value;
        }

        foreach ($definitions as $definition) {
            if (!array_key_exists($definition->id, $values)) continue;
            $definition->value = $values[$definition->id];
            $definition->updated_by = auth()->id();
            $definition->save();
        }

        MailBackupAuditLog::create([
            'actor_id' => auth()->id(), 'action' => 'DYNAMIC_SETTINGS_UPDATED',
            'entity_type' => 'settings', 'result' => 'SUCCESS',
            'details' => ['updated_fields' => count($values)], 'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Đã lưu các thông số cấu hình Email Admin.');
    }

    public function updateDefinition(Request $request, MailBackupSettingDefinition $definition)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'group' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $definition->update([
            'label' => $data['label'], 'group' => $data['group'],
            'description' => $data['description'] ?? null, 'sort_order' => (int) $data['sort_order'],
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false), 'updated_by' => auth()->id(),
        ]);
        $this->audit('SETTING_DEFINITION_UPDATED', $definition, ['key' => $definition->key, 'is_active' => $definition->is_active]);
        return back()->with('success', 'Đã cập nhật định nghĩa cấu hình.');
    }

    private function validateValue(string $type, string $value, ?array $options, bool $required): void
    {
        if ($required && $value === '') throw ValidationException::withMessages(['value' => 'Trường cấu hình bắt buộc không được để trống.']);
        if ($value === '') return;

        $valid = match ($type) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false && in_array(parse_url($value, PHP_URL_SCHEME), ['http','https'], true),
            'number' => is_numeric($value),
            'select' => is_array($options) && array_key_exists($value, $options),
            'boolean' => in_array($value, ['0','1','true','false'], true),
            default => true,
        };
        if (!$valid) throw ValidationException::withMessages(['value' => "Giá trị không đúng kiểu {$type}."]);
    }

    private function audit(string $action, MailBackupSettingDefinition $definition, array $details): void
    {
        MailBackupAuditLog::create([
            'actor_id' => auth()->id(), 'action' => $action, 'entity_type' => 'setting_definition',
            'entity_id' => $definition->id, 'result' => 'SUCCESS', 'details' => $details,
            'ip_address' => request()->ip(),
        ]);
    }
}
