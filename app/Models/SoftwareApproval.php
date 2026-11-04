<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SoftwareApproval extends Model
{
 protected $fillable=['software_request_id','stage','decision','comment','approver_id','decided_at','metadata'];
 protected $casts=['decided_at'=>'datetime','metadata'=>'array'];
}