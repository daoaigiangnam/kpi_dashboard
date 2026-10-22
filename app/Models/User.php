<?php

namespace App\Models;

use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements CanResetPasswordContract
{
    use Notifiable, SoftDeletes, CanResetPasswordTrait;

    protected $fillable = [
        'employee_code','external_id','name','email','phone','date_of_birth','gender',
        'join_date','department','location','department_id','unit_id','user_group_id',
        'job_title_id','notes','password','is_active','registration_status',
        'registration_reviewed_at','registration_reviewed_by','registration_rejection_reason',
        'account_type','service_customer_id','can_view_assets','can_view_audit','can_download_qr',
    ];

    protected $hidden = ['password','remember_token'];
    protected $casts = [
        'is_active'=>'boolean','date_of_birth'=>'date','join_date'=>'date',
        'registration_reviewed_at'=>'datetime','password'=>'hashed',
        'can_view_assets'=>'boolean','can_view_audit'=>'boolean','can_download_qr'=>'boolean',
    ];

    public function group(): BelongsTo { return $this->belongsTo(UserGroup::class,'user_group_id'); }
    public function jobTitle(): BelongsTo { return $this->belongsTo(JobTitle::class); }
    public function departmentRelation(): BelongsTo { return $this->belongsTo(Department::class,'department_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class,'unit_id'); }
    public function registrationReviewer(): BelongsTo { return $this->belongsTo(self::class,'registration_reviewed_by'); }
    public function customer(): BelongsTo { return $this->belongsTo(ServiceCustomer::class,'service_customer_id'); }

    /** Customers for which this User is the assigned IT operations owner. */
    public function serviceCustomers(): HasMany
    {
        return $this->hasMany(ServiceCustomer::class, 'responsible_it_id');
    }

    public function isCustomerAccount(): bool
    {
        return $this->account_type === 'customer' && $this->service_customer_id !== null;
    }

    public function hasPermission(string $permission): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Customer accounts never inherit internal administrator permissions.
        if ($this->account_type === 'customer') {
            return match ($permission) {
                'customer.assets.view' => $this->can_view_assets,
                'customer.audit.view' => $this->can_view_audit,
                'customer.assets.qr' => $this->can_view_assets && $this->can_download_qr,
                default => false,
            };
        }

        // PC Audit package buttons are explicitly configurable even for Super Admin.
        $perButtonPermissions = [
            'pc_audit.package_windows7',
            'pc_audit.package_windows10',
            'pc_audit.package_nodejs',
        ];

        if ($this->isSuperAdmin() && !in_array($permission, $perButtonPermissions, true)) {
            return true;
        }

        return $this->group?->permissions()->where('code', $permission)->exists() ?? false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->account_type !== 'customer' && $this->group?->name === 'Super Admin';
    }
}
