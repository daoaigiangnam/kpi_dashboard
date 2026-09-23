<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PcAuditSoftware extends Model { protected $table='pc_audit_software'; protected $guarded=[]; protected $casts=['estimated_size'=>'decimal:2']; public function audit(): BelongsTo { return $this->belongsTo(PcAudit::class,'pc_audit_id'); } }
