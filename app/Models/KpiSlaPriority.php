<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiSlaPriority extends Model
{
    protected $table = 'kpi_sla_priorities';

    protected $fillable = [
        'code', 'description', 'response_minutes', 'resolution_minutes', 'weight', 'workload_point', 'sort_order',
    ];

    protected $casts = [
        'response_minutes' => 'integer', 'resolution_minutes' => 'integer', 'weight' => 'integer',
        'workload_point' => 'integer', 'sort_order' => 'integer',
    ];
}
