<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class UserGroup extends Model {
 protected $fillable=['name','description','is_system']; protected $casts=['is_system'=>'boolean'];
 public function permissions(): BelongsToMany { return $this->belongsToMany(Permission::class,'group_permissions'); }
 public function users(){ return $this->hasMany(User::class,'user_group_id'); }
}
