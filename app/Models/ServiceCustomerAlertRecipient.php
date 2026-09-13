<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCustomerAlertRecipient extends Model
{
    protected $fillable = [
        'customer_id',
        'level',
        'recipient_name',
        'recipient_email',
        'recipient_phone',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_active' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(ServiceCustomer::class, 'customer_id');
    }
}
