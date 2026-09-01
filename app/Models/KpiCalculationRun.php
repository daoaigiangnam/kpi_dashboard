<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiCalculationRun extends Model
{
    protected $fillable = [
        'run_code', 'employee_id', 'period_from', 'period_to', 'criteria',
        'weights_snapshot', 'metrics', 'total_kpi', 'calculated_by', 'calculated_at',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'criteria' => 'array',
        'weights_snapshot' => 'array',
        'metrics' => 'array',
        'total_kpi' => 'decimal:4',
        'calculated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }
}
