<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PcAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'pc_audit_code_id','department','employee_name','employee_username','domain','computer_name','manufacturer','model','serial_number','asset_tag',
        'mainboard','bios','operating_system','windows_update','last_boot','uptime','tpm','secure_boot','collected_at',
        'audit_status','audit_score','audit_results','audit_engine_version','raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'mainboard'=>'array','bios'=>'array','operating_system'=>'array','windows_update'=>'array','tpm'=>'array',
            'secure_boot'=>'boolean',
            'audit_score'=>'integer','audit_results'=>'array','raw_payload'=>'array','collected_at'=>'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('pc_audit_responsible_group', function ($builder): void {
            // Only apply the responsible-IT visibility rule inside the PC Audit admin module.
            // The Audit Agent API is intentionally not affected by this scope.
            if (!function_exists('request') || !request()->routeIs('admin.pc_audit.*')) {
                return;
            }

            $user = auth()->user();
            if (!$user || $user->isSuperAdmin()) {
                return;
            }

            $groupId = $user->user_group_id;
            if (!$groupId) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->where(function ($query) use ($groupId): void {
                // New rule: visibility follows the Customer's Responsible IT user's group.
                $query->whereHas('auditCode.branch.customer.responsibleIt', function ($responsible) use ($groupId): void {
                    $responsible->where('user_group_id', $groupId);
                });

                // Backward compatibility for existing Customers that have not yet been
                // assigned a Responsible IT user: keep the previous Customer -> Group mapping.
                $query->orWhereHas('auditCode.branch.customer.groups', function ($groups) use ($groupId): void {
                    $groups->whereKey($groupId);
                });
            });
        });
    }

    public function auditCode(): BelongsTo { return $this->belongsTo(PcAuditCode::class, 'pc_audit_code_id'); }
    public function details(): HasOne { return $this->hasOne(PcAuditDetail::class, 'pc_audit_id'); }
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
