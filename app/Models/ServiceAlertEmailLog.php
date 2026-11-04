<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAlertEmailLog extends Model
{
    protected $fillable = [
        'service_alert_event_id', 'level', 'recipient_type', 'recipient_email',
        'email_type', 'sent_at', 'status', 'error',
    ];

    protected $casts = [
        'level' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ServiceAlertEvent::class, 'service_alert_event_id');
    }
}
