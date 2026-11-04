<?php

namespace App\Console\Commands;

use App\Models\MailBackupJob;
use Illuminate\Console\Command;

class MailBackupQueueDryRunCommand extends Command
{
    protected $signature = 'mail-backup:queue {--limit=100 : Maximum queued jobs to show}';
    protected $description = 'Inspect Mail Backup Manager queue without processing email';

    public function handle(): int
    {
        $limit = max(1, min((int) $this->option('limit'), 1000));
        $jobs = MailBackupJob::query()
            ->with('batch:id,batch_code')
            ->whereIn('status', ['PENDING', 'RETRY_WAIT'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $this->info("DRY RUN — {$jobs->count()} queued jobs. No email is accessed and no job is changed.");
        $this->table(
            ['Job', 'Batch', 'Email', 'Status', 'Attempt'],
            $jobs->map(fn ($job) => [
                $job->id, $job->batch?->batch_code, $job->email, $job->status, $job->attempt_count,
            ])->all()
        );

        if ($jobs->isNotEmpty()) {
            $this->warn('Gmail/PST worker is not enabled in Phase 1. Queue processing is intentionally disabled.');
        }

        return self::SUCCESS;
    }
}
