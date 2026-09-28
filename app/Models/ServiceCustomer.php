<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCustomer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'contact_name',
        'email',
        'phone',
        'responsible_it_id',
        'sales_contact_id',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::addGlobalScope('pc_audit_responsible_group', function ($builder): void {
            if (!function_exists('request') || !request()->routeIs('admin.pc_audit.*')) {
                return;
            }

            $user = auth()->user();
            if (!$user || $user->isSuperAdmin()) {
                return;
            }

            $groupId = $user->user_group_id;
            if (!$groupId) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->whereHas('groups', function ($query) use ($groupId): void {
                $query->whereKey($groupId);
            });
        });
    }

    public function responsibleIt(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_it_id');
    }

    public function salesContact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_contact_id');
    }

    public function alertRecipients(): HasMany
    {
        return $this->hasMany(ServiceCustomerAlertRecipient::class, 'customer_id')->orderBy('level');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'customer_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(CustomerBranch::class, 'customer_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'service_customer_group', 'service_customer_id', 'user_group_id');
    }

    public function isAssignedToGroup(?int $groupId): bool
    {
        return $groupId !== null && $this->groups()->whereKey($groupId)->exists();
    }
}
