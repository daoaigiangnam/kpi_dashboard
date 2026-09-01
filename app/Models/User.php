<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'user_group_id'];
    protected $hidden = ['password', 'remember_token'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    public function isAdmin(): bool
    {
        return $this->group?->name === 'Super Admin';
    }

    public function hasPermission(string $permission): bool
    {
        return $this->group?->permissions()->where('code', $permission)->exists() ?? false;
    }
}
