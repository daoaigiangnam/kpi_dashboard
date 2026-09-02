<?php

namespace App\Models;

use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable implements CanResetPasswordContract
{
    use Notifiable, SoftDeletes, CanResetPasswordTrait;

    protected $fillable = ['employee_code','name','email','phone','date_of_birth','gender','join_date','department','location','department_id','unit_id','user_group_id','job_title_id','notes','password','is_active','registration_status','registration_reviewed_at','registration_reviewed_by','registration_rejection_reason'];
    protected $hidden = ['password','remember_token'];
    protected $casts = ['is_active'=>'boolean','date_of_birth'=>'date','join_date'=>'date','registration_reviewed_at'=>'datetime','password'=>'hashed'];

    public function group(): BelongsTo { return $this->belongsTo(UserGroup::class,'user_group_id'); }
    public function jobTitle(): BelongsTo { return $this->belongsTo(JobTitle::class); }
    public function departmentRelation(): BelongsTo { return $this->belongsTo(Department::class,'department_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class,'unit_id'); }
    public function registrationReviewer(): BelongsTo { return $this->belongsTo(self::class,'registration_reviewed_by'); }

    public function hasPermission(string $permission): bool
    {
        return $this->is_active && ($this->isSuperAdmin() || ($this->group?->permissions()->where('code',$permission)->exists() ?? false));
    }

    public function isSuperAdmin(): bool { return $this->group?->name === 'Super Admin'; }
}
