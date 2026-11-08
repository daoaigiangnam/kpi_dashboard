<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CommandAuditSession extends Model {
 protected $fillable=['server_id','session_id','status','started_by','started_at','stopped_at','event_count','report_path','metadata'];
 protected $casts=['started_at'=>'datetime','stopped_at'=>'datetime','metadata'=>'array'];
}