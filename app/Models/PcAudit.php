<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PcAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'pc_audit_code_id',
        'department',
        'employee_name',
        'employee_username',
        'domain',
        'computer_name',
        'manufacturer',
        'model',
        'serial_number',
        'asset_tag',
        'collected_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function auditCode(): BelongsTo
    {
        return $this->belongsTo(PcAuditCode::class, 'pc_audit_code_id');
    }

    public function details(): HasOne
    {
        return $this->hasOne(PcAuditDetail::class);
    }
}
