<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['employee_code','name','email','phone','date_of_birth','gender','join_date','department','location','user_group_id','job_title_id','notes','password','is_active'];
    protected $hidden = ['password','remember_token'];
    protected $casts = ['is_active'=>'boolean','date_of_birth'=>'date','join_date'=>'date','password'=>'hashed'];

    public function group(): BelongsTo { return $this->belongsTo(UserGroup::class,'user_group_id'); }
    public function jobTitle(): BelongsTo { return $this->belongsTo(JobTitle::class); }

    public function hasPermission(string $permission): bool
    {
        return $this->is_active && ($this->isSuperAdmin() || ($this->group?->permissions()->where('code',$permission)->exists() ?? false));
    }

    public function isSuperAdmin(): bool { return $this->group?->name === 'Super Admin'; }
}
