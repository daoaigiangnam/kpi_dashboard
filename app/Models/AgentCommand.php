<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AgentCommand extends Model {
 protected $fillable=['server_id','command_id','type','payload','status','requested_by','requested_at','delivered_at','lease_expires_at','delivery_attempts','completed_at','expires_at','result','error'];
 protected $casts=['payload'=>'array','result'=>'array','requested_at'=>'datetime','delivered_at'=>'datetime','lease_expires_at'=>'datetime','delivery_attempts'=>'integer','completed_at'=>'datetime','expires_at'=>'datetime'];
}