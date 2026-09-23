<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PcAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'pc_audit_code_id','department','employee_name','employee_username','domain','computer_name','manufacturer','model','serial_number','asset_tag',
        'mainboard','bios','operating_system','windows_update','last_boot','uptime','tpm','secure_boot','collected_at','raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'mainboard'=>'array','bios'=>'array','operating_system'=>'array','windows_update'=>'array','tpm'=>'array','raw_payload'=>'array','collected_at'=>'datetime',
        ];
    }

    public function auditCode(): BelongsTo { return $this->belongsTo(PcAuditCode::class, 'pc_audit_code_id'); }
    public function memory(): HasMany { return $this->hasMany(PcAuditMemory::class); }
    public function storage(): HasMany { return $this->hasMany(PcAuditStorage::class); }
    public function monitors(): HasMany { return $this->hasMany(PcAuditMonitor::class); }
    public function gpu(): HasMany { return $this->hasMany(PcAuditGpu::class); }
    public function batteries(): HasMany { return $this->hasMany(PcAuditBattery::class); }
    public function network(): HasMany { return $this->hasMany(PcAuditNetwork::class); }
    public function antivirus(): HasMany { return $this->hasMany(PcAuditAntivirus::class); }
    public function bitlocker(): HasMany { return $this->hasMany(PcAuditBitlocker::class); }
    public function firewall(): HasMany { return $this->hasMany(PcAuditFirewall::class); }
    public function licenses(): HasMany { return $this->hasMany(PcAuditLicense::class); }
    public function software(): HasMany { return $this->hasMany(PcAuditSoftware::class); }
}
