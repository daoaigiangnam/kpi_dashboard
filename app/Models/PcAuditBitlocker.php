<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PcAuditBitlocker extends Model { protected $table='pc_audit_bitlocker'; protected $guarded=[]; protected $casts=['encryption_percent'=>'decimal:2']; public function audit(): BelongsTo { return $this->belongsTo(PcAudit::class,'pc_audit_id'); } }
