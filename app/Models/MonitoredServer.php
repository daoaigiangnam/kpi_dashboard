<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class MonitoredServer extends Model {
 protected $fillable=['agent_id','name','hostname','server_code','token_hash','status','last_seen_at','agent_version','os_info','metadata','audit_enabled'];
 protected $hidden=['token_hash'];
 protected $casts=['last_seen_at'=>'datetime','os_info'=>'array','metadata'=>'array','audit_enabled'=>'boolean'];
 public function commands():HasMany{return $this->hasMany(AgentCommand::class,'server_id');}
 public function auditSessions():HasMany{return $this->hasMany(CommandAuditSession::class,'server_id');}
 public function auditEvents():HasMany{return $this->hasMany(CommandAuditEvent::class,'server_id');}
}