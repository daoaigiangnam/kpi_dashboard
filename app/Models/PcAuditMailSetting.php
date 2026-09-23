<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PcAuditMailSetting extends Model
{
    protected $fillable = [
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'from_email', 'from_name', 'enabled',
    ];

    protected $casts = ['enabled' => 'boolean'];

    protected $hidden = ['smtp_password'];
}
