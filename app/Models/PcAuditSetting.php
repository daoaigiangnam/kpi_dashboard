<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PcAuditSetting extends Model
{
    protected $fillable = [
        'api_base_url',
        'tool_version',
        'minimum_tool_version',
        'enabled',
        'download_url',
        'disabled_message',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
