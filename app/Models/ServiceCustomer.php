<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCustomer extends Model
{
    use SoftDeletes;

    protected $fillable = ['code','name','contact_name','email','phone','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function alertRecipients()
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
