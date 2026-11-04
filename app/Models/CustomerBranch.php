<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerBranch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id', 'code', 'name', 'address', 'contact_name', 'phone', 'email', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(ServiceCustomer::class, 'customer_id');
    }

    public function pcAuditCodes(): HasMany
    {
        return $this->hasMany(PcAuditCode::class, 'branch_id');
    }
}
