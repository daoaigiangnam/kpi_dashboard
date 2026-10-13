<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\MailBackupAuditLog;
use App\\Models\\MailBackupSettingDefinition;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Validation\\Rule;
use Illuminate\\Validation\\ValidationException;

class MailBackupSettingController extends Controller
{
    public function index()
    {
        $definitions = MailBackupSettingDefinition::query()->orderBy('group')->orderBy('sort_order')->orderBy('label')->get();
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
        if (($data['type'] ?? '') === 'select' && !$options) {
            throw ValidationException::withMessages(['options_json' => 'Trường select cần khai báo ít nhất một option.']);
        }

        $definition = MailBackupSettingDefinition::create([
            'key' => $data['key'],
            'label' => $data['label'],
            'group' => $data['group'],
            'type' => $data['type'],
            'value' => $data['value'] ?? null,
            'description' => $data['description'] ?? null,
            'validation_rules' => null,
            'options' => $options,
            'is_secret' => (bool) ($data['is_secret'] ?? false) || $data['type'] === 'password',
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_by' => auth()->id(),
        ]);

        $this->audit('SETTING_DEFINITION_CREATED', $definition, ['key' => $definition->key, 'type' => $definition->type]);
        return redirect()->route('admin.mail_backup.settings')->with('success', 'Đã thêm thông số cấu hình động.');
    }

    public function updateValues(Request $request)
    {
        $definitions = MailBackupSettingDefinition::query()->where('is_active', true)->get();
        $input = $request->input('settings', []);
        if (!is_array($input)) throw ValidationException::withMessages(['settings' => 'Dữ liệu cấu hình không hợp lệ.']);

        $validatedValues = [];
        foreach ($definitions as $definition) {
            $present = array_key_exists((string) $definition->id, $input);
            $raw = $present ? $input[(string) $definition->id] : null;

            if (!$present && $definition->is_required && !$definition->is_secret) {
                throw ValidationException::withMessages(["settings.{$definition->id}" => "Thiếu giá trị {$definition->label}."]);
            }

            if ($definition->is_secret && !$present) {
                continue; // Blank password input means keep existing encrypted value.
            }

            if ($definition->type === 'boolean') {
                $value = $present && in_array((string) $raw, ['1', 'true', 'on'], true) ? '1' : '0';
            } else {
                $value = is_scalar($raw) ? trim((string) $raw) : '';
            }

            if ($definition->is_required && $value === '') {
                throw ValidationException::withMessages(["settings.{$definition->id}" => "{$definition->label} là bắt buộc."]);
            }

            if ($value !== '') {
                $valid = match ($definition->type) {
                    'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                    'url' => filter_var($value, FILTER_VALIDATE_URL) !== false && in_array(parse_url($value, PHP_URL_SCHEME), ['http','https'], true),
                    'number' => is_numeric($value),
                    'select' => is_array($definition->options) && array_key_exists($value, $definition->options),
                    default => true,
                };
                if (!$valid) throw ValidationException::withMessages(["settings.{$definition->id}" => "Giá trị {$definition->label} không hợp lệ."]);
            }

            $validatedValues[$definition->id] = $value;
        }

        DB::transaction(function () use ($definitions, $validatedValues) {
            foreach ($definitions as $definition) {
                if (!array_key_exists($definition->id, $validatedValues)) continue;
                $definition->value = $validatedValues[$definition->id];
                $definition->updated_by = auth()->id();
                $definition->save();
            }
        });

        MailBackupAuditLog::create([
            'actor_id' => auth()->id(), 'action' => 'DYNAMIC_SETTINGS_UPDATED',
            'entity_type' => 'settings', 'entity_id' => null, 'result' => 'SUCCESS',
            'details' => ['updated_fields' => count($validatedValues)], 'ip_address' => $request->ip(),
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
            'label' => $data['label'],
            'group' => $data['group'],
            'description' => $data['description'] ?? null,
            'sort_order' => (int) $data['sort_order'],
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'updated_by' => auth()->id(),
        ]);

        $this->audit('SETTING_DEFINITION_UPDATED', $definition, [
            'key' => $definition->key, 'is_active' => $definition->is_active,
        ]);
        return back()->with('success', 'Đã cập nhật định nghĩa cấu hình.');
    }

    private function audit(string $action, MailBackupSettingDefinition $definition, array $details): void
    {
        MailBackupAuditLog::create([
            'actor_id' => auth()->id(), 'action' => $action,
            'entity_type' => 'setting_definition', 'entity_id' => $definition->id,
            'result' => 'SUCCESS', 'details' => $details, 'ip_address' => request()->ip(),
        ]);
    }
}
