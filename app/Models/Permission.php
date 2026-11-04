<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Permission extends Model {
 protected $fillable=['module','name','code','description'];
 public function groups(): BelongsToMany { return $this->belongsToMany(UserGroup::class,'group_permissions'); }
}
