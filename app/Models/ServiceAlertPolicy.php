<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAlertPolicy extends Model
{
    protected $fillable = ['service_type_id', 'name', 'alert_1_percent', 'alert_2_percent', 'alert_3_percent', 'is_active'];

    protected $casts = [
        'alert_1_percent' => 'decimal:2',
        'alert_2_percent' => 'decimal:2',
        'alert_3_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }
}
