<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceMonitorEvent extends Model
{
    protected $fillable = [
        'service_id', 'event_type', 'check_method', 'target', 'port', 'status',
        'latency_ms', 'packet_loss_percent', 'started_at', 'resolved_at',
        'duration_seconds', 'error',
    ];

    protected $casts = [
        'port' => 'integer',
        'latency_ms' => 'decimal:1',
        'packet_loss_percent' => 'decimal:2',
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
