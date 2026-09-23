<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PcAuditBattery extends Model { protected $table='pc_audit_battery'; protected $guarded=[]; public function audit(): BelongsTo { return $this->belongsTo(PcAudit::class,'pc_audit_id'); } }
