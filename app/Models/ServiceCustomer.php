<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCustomer extends Model
{
    use SoftDeletes;

    protected $fillable = ['code','name','contact_name','email','phone','is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
