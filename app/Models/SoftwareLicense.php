<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SoftwareLicense extends Model
{
 protected $fillable=['software_name','vendor','version','license_type','quantity','assigned_user','device_name','asset_code','installed_at','expires_at','ticket_code','status','evidence_path','notes'];
 protected $casts=['installed_at'=>'date','expires_at'=>'date','quantity'=>'integer'];
}