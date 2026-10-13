<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailBackupSettingDefinition extends Model
{
    protected $fillable = [
        'key', 'label', 'group', 'type', 'value', 'description',
        'validation_rules', 'options', 'is_secret', 'is_required',
        'is_active', 'sort_order', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'validation_rules' => 'array',
            'options' => 'array',
            'is_secret' => 'boolean',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function resolvedValue(): ?string
    {
        if (!$this->is_secret || blank($this->value)) {
            return $this->value;
        }

        try {
            // Secret values are explicitly encrypted by the settings controller.
            return decrypt($this->value);
        } catch (\\Throwable $e) {
            return null;
        }
    }

    public function displayValue(): ?string
    {
        if ($this->is_secret && filled($this->value)) {
            return '•••••••• (đã cấu hình)';
        }

        return $this->value;
    }
}
