<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CommandAuditEvent extends Model {
 public $timestamps=false;
 protected $fillable=['server_id','session_id','event_key','event_time','username','uid','auid','pid','ppid','executable','arguments','audit_serial','source_ip','raw_event','created_at'];
 protected $casts=['event_time'=>'datetime','arguments'=>'array','raw_event'=>'array','created_at'=>'datetime'];
}