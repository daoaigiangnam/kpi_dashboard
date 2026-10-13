<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class MailBackupJobLog extends Model
{
    protected $fillable = ['job_id', 'attempt_no', 'level', 'message', 'context'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(MailBackupJob::class, 'job_id');
    }
}
