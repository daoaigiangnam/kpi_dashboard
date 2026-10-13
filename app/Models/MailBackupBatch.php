<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;

class MailBackupBatch extends Model
{
    protected $fillable = [
        'batch_code', 'name', 'date_from', 'date_to', 'mail_scope', 'status',
        'total_jobs', 'pending_jobs', 'running_jobs', 'completed_jobs', 'failed_jobs', 'created_by',
    ];

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_to' => 'date'];
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(MailBackupJob::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function refreshCounters(): void
    {
        $counts = $this->jobs()
            ->selectRaw("COUNT(*) AS total")
            ->selectRaw("SUM(status IN ('PENDING','RETRY_WAIT')) AS pending")
            ->selectRaw("SUM(status IN ('RUNNING','VERIFYING')) AS running")
            ->selectRaw("SUM(status = 'COMPLETED') AS completed")
            ->selectRaw("SUM(status = 'FAILED') AS failed")
            ->first();

        $this->total_jobs = (int) ($counts->total ?? 0);
        $this->pending_jobs = (int) ($counts->pending ?? 0);
        $this->running_jobs = (int) ($counts->running ?? 0);
        $this->completed_jobs = (int) ($counts->completed ?? 0);
        $this->failed_jobs = (int) ($counts->failed ?? 0);

        if ($this->total_jobs > 0 && $this->completed_jobs === $this->total_jobs) {
            $this->status = 'COMPLETED';
        } elseif ($this->pending_jobs === 0 && $this->running_jobs === 0 && $this->failed_jobs > 0 && $this->completed_jobs === 0) {
            $this->status = 'FAILED';
        } elseif ($this->pending_jobs === 0 && $this->running_jobs === 0 && $this->failed_jobs > 0) {
            $this->status = 'PARTIAL_FAILED';
        } elseif ($this->running_jobs > 0 || $this->completed_jobs > 0) {
            $this->status = 'RUNNING';
        } else {
            $this->status = 'PENDING';
        }

        $this->save();
    }
}
