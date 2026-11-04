<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\ItTools\ServiceAlertEmailService;
use App\Services\ItTools\ServiceAlertEngine;
use Illuminate\Console\Command;

class ServiceMonitoringCommand extends Command
{
    protected $signature = 'services:monitor {--limit=500 : Maximum services to evaluate per run}';
    protected $description = 'Evaluate IT services and Website SSL certificates for expiry alerts using their configured Alert Policy.';

    public function handle(ServiceAlertEngine $engine, ServiceAlertEmailService $emailService): int
    {
        $limit = max(1, min((int) $this->option('limit'), 5000));
        $count = 0;
        $created = 0;

        Service::query()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where(function ($x) {
                    $x->whereNotNull('expiry_date')->whereNotNull('alert_policy_id');
                })->orWhere(function ($x) {
                    $x->where('ssl_detected', true)->whereNotNull('ssl_expiry_date')->whereNotNull('alert_policy_id');
                });
            })
            ->with('alertPolicy')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Service $service) use ($engine, $emailService, &$count, &$created) {
                $event = $engine->evaluate($service);
                if ($event) {
                    $emailService->notifyNewAlert($event);
                    $created++;
                }

                $sslEvent = $engine->evaluateSsl($service);
                if ($sslEvent) {
                    $emailService->notifyNewAlert($sslEvent);
                    $created++;
                }

                $count++;
            });

        $this->info("Services evaluated: {$count}; alerts created: {$created}");
        return self::SUCCESS;
    }
}
