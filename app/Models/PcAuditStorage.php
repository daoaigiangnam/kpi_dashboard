<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PcAuditStorage extends Model { protected $table='pc_audit_storage'; protected $guarded=[]; protected $casts=['used_gb'=>'decimal:2','total_gb'=>'decimal:2']; public function audit(): BelongsTo { return $this->belongsTo(PcAudit::class,'pc_audit_id'); } }
