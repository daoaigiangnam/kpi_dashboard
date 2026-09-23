<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PcAuditFirewall extends Model { protected $table='pc_audit_firewall'; protected $guarded=[]; protected $casts=['enabled'=>'boolean']; public function audit(): BelongsTo { return $this->belongsTo(PcAudit::class,'pc_audit_id'); } }
