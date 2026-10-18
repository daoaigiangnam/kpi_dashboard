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
        'agent_schedule_enabled',
        'agent_interval_minutes',
        'agent_run_on_start',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'agent_schedule_enabled' => 'boolean',
        'agent_run_on_start' => 'boolean',
        'agent_interval_minutes' => 'integer',
    ];
}
