<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
class JobTitle extends Model
{
 use SoftDeletes;
 protected $fillable=['code','name','level','description','target_workload_point','is_active'];
 protected $casts=['target_workload_point'=>'decimal:2','is_active'=>'boolean'];
 public function users():HasMany{return $this->hasMany(User::class);}
}
