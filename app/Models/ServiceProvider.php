<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceProvider extends Model
{
    use SoftDeletes;

    protected $fillable = ['code','name','website','support_contact','is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
