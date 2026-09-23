<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PcAuditDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'pc_audit_id',
        'hardware',
        'cpu',
        'memory',
        'storage',
        'monitors',
        'gpu',
        'battery',
        'windows',
        'network',
        'security',
        'licenses',
        'software',
        'other',
    ];

    protected function casts(): array
    {
        return [
            'hardware' => 'array',
            'cpu' => 'array',
            'memory' => 'array',
            'storage' => 'array',
            'monitors' => 'array',
            'gpu' => 'array',
            'battery' => 'array',
            'windows' => 'array',
            'network' => 'array',
            'security' => 'array',
            'licenses' => 'array',
            'software' => 'array',
            'other' => 'array',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(PcAudit::class, 'pc_audit_id');
    }
}
