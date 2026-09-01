<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable; use Illuminate\Notifications\Notifiable; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class User extends Authenticatable { use Notifiable; protected $fillable=['name','email','password','user_group_id','is_active']; protected $hidden=['password','remember_token']; protected $casts=['is_active'=>'boolean','password'=>'hashed']; public function group():BelongsTo{return $this->belongsTo(UserGroup::class,'user_group_id');} public function hasPermission(string $permission):bool{return $this->is_active && ($this->isSuperAdmin() || ($this->group?->permissions()->where('code',$permission)->exists() ?? false));} public function isSuperAdmin():bool{return $this->group?->name==='Super Admin';} }
