<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItToolAudit extends Model
{
    protected $fillable = [
        'user_id', 'domain', 'wan_ip', 'status', 'duration_ms', 'result', 'error',
    ];

    protected $casts = [
        'result' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
