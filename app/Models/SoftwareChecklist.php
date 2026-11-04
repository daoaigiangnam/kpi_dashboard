<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SoftwareChecklist extends Model
{
 protected $fillable=['software_request_id','item_key','item_label','passed','evidence','checked_by','checked_at'];
 protected $casts=['passed'=>'boolean','checked_at'=>'datetime'];
}