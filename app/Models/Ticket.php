<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'external_ticket_id', 'employee_id', 'priority', 'created_on', 'started_on', 'finished_on',
        'pause_minutes', 'reopen_count', 'company_department', 'resolution_detail', 'result_screenshot',
        'workload_point', 'resolution_minutes', 'sla_target_minutes', 'sla_status', 'process_status',
        'started_status', 'source', 'source_file', 'source_payload',
    ];

    protected $casts = [
        'created_on' => 'datetime', 'started_on' => 'datetime', 'finished_on' => 'datetime',
        'pause_minutes' => 'integer', 'reopen_count' => 'integer', 'workload_point' => 'decimal:2',
        'resolution_minutes' => 'integer', 'sla_target_minutes' => 'integer', 'source_payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $ticket): void {
            $priority = KpiSlaPriority::query()
                ->where('code', strtoupper((string) $ticket->priority))
                ->first();
            $ticket->workload_point = $ticket->finished_on !== null
                ? (float) ($priority?->workload_point ?? 0)
                : 0;
        });
    }

    public function getWorkloadPointAttribute($value)
    {
        $priority = KpiSlaPriority::query()
            ->where('code', strtoupper((string) $this->priority))
            ->first();
        return $priority?->workload_point ?? $value;
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
