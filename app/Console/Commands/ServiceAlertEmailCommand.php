<?php

namespace App\Console\Commands;

use App\Services\ItTools\ServiceAlertEmailService;
use Illuminate\Console\Command;

class ServiceAlertEmailCommand extends Command
{
    protected $signature = 'services:alert-emails';
    protected $description = 'Send configured IT Monitoring alert escalation emails.';

    public function handle(ServiceAlertEmailService $service): int
    {
        $sent = $service->processEscalations();
        $this->info("Escalation emails sent: {$sent}");

        return self::SUCCESS;
    }
}
