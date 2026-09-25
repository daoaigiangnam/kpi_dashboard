<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceMonitorEmailLog extends Model
{
    protected $fillable = [
        'service_monitor_event_id', 'level', 'recipient_type', 'recipient_email',
        'email_type', 'sent_at', 'status', 'error',
    ];

    protected $casts = [
        'level' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ServiceMonitorEvent::class, 'service_monitor_event_id');
    }
}
