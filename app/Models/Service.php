<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id', 'service_type_id', 'provider_id', 'service_name', 'value',
        'service_term_months', 'expiry_date', 'alert_policy_id', 'responsible_it_id',
        'status', 'auto_renew', 'note', 'alert_stage', 'last_alert_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'last_alert_at' => 'datetime',
        'auto_renew' => 'boolean',
        'service_term_months' => 'integer',
        'alert_stage' => 'integer',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(ServiceCustomer::class); }
    public function serviceType(): BelongsTo { return $this->belongsTo(ServiceType::class); }
    public function provider(): BelongsTo { return $this->belongsTo(ServiceProvider::class); }
    public function alertPolicy(): BelongsTo { return $this->belongsTo(ServiceAlertPolicy::class); }
    public function responsibleIt(): BelongsTo { return $this->belongsTo(User::class, 'responsible_it_id'); }
}
