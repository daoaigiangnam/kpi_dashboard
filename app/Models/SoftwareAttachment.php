<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SoftwareAttachment extends Model
{
 protected $fillable=['software_request_id','category','original_name','path','mime_type','size_bytes','uploaded_by'];
}