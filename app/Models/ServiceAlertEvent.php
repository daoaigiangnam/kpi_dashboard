<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAlertEvent extends Model
{
    protected $fillable = [
        'service_id', 'alert_policy_id', 'alert_stage', 'remaining_percent', 'expiry_date',
        'status', 'triggered_at', 'acknowledged_at', 'acknowledged_by',
        'resolved_at', 'resolved_by', 'note',
    ];

    protected $casts = [
        'alert_stage' => 'integer',
        'remaining_percent' => 'decimal:2',
        'expiry_date' => 'date',
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function alertPolicy(): BelongsTo { return $this->belongsTo(ServiceAlertPolicy::class); }
    public function acknowledgedBy(): BelongsTo { return $this->belongsTo(User::class, 'acknowledged_by'); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
}
