<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SoftwareAssessment extends Model
{
 protected $fillable=['software_request_id','assessor_id','source_url','compatibility_result','admin_rights_required','data_access_scope','security_risk','license_type','license_quantity','license_until','license_evidence','it_conclusion','conditions','notes','assessed_at'];
 protected $casts=['license_until'=>'date','assessed_at'=>'datetime','admin_rights_required'=>'boolean'];
}