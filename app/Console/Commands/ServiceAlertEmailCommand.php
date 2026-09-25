<?php

namespace App\Console\Commands;

use App\Services\ItTools\NetworkAlertEmailService;
use App\Services\ItTools\ServiceAlertEmailService;
use Illuminate\Console\Command;

class ServiceAlertEmailCommand extends Command
{
    protected $signature = 'services:alert-emails';
    protected $description = 'Send configured IT Monitoring alert escalation emails.';

    public function handle(ServiceAlertEmailService $service, NetworkAlertEmailService $networkAlerts): int
    {
        $sent = $service->processEscalations();
        $networkSent = $networkAlerts->process();
        $this->info("Escalation emails sent: {$sent}; network alert emails sent: {$networkSent}");

        return self::SUCCESS;
    }
}
