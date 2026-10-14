<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SoftwareInstallation extends Model
{
 protected $fillable=['software_request_id','technician_id','started_at','finished_at','result','functionality_check','security_check','readonly_check','issues','rollback_plan','user_acceptance','user_acceptance_by','user_accepted_at','it_confirmed_at','notes'];
 protected $casts=['started_at'=>'datetime','finished_at'=>'datetime','user_accepted_at'=>'datetime','it_confirmed_at'=>'datetime'];
}