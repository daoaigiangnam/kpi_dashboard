<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailBackupAuditLog extends Model
{
    protected $fillable = ['actor_id', 'action', 'entity_type', 'entity_id', 'result', 'details', 'ip_address'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
