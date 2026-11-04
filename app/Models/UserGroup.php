<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserGroup extends Model
{
    use SoftDeletes;

    protected $fillable=['name','description','is_system'];
    protected $casts=['is_system'=>'boolean'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class,'group_permissions');
    }

    public function users()
    {
        return $this->hasMany(User::class,'user_group_id');
    }

    public function serviceCustomers(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCustomer::class, 'service_customer_group', 'user_group_id', 'service_customer_id');
    }
}
