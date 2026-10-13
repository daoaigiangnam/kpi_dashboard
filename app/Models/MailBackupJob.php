<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailBackupJob extends Model
{
    protected $fillable = [
        'batch_id', 'email', 'display_name', 'status', 'attempt_count',
        'processed_messages', 'expected_messages', 'pst_path', 'pst_size',
        'last_error', 'worker_id', 'locked_at', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MailBackupBatch::class, 'batch_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MailBackupJobLog::class, 'job_id')->latest();
    }
}
