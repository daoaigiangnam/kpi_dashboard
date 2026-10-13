<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;

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
            'value' => 'encrypted',
            'validation_rules' => 'array',
            'options' => 'array',
            'is_secret' => 'boolean',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function displayValue(): ?string
    {
        if ($this->is_secret && filled($this->value)) {
            return '•••••••• (đã cấu hình)';
        }

        return $this->value;
    }
}
