<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\ServiceAlertEngine;
use Illuminate\Console\Command;

class ServiceMonitoringCommand extends Command
{
    protected $signature = 'services:monitor {--limit=500 : Maximum services to evaluate per run}';
    protected $description = 'Evaluate IT services for expiry alerts using their configured Alert Policy.';

    public function handle(ServiceAlertEngine $engine): int
    {
        $limit = max(1, min((int) $this->option('limit'), 5000));
        $count = 0;

        Service::query()
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereNotNull('alert_policy_id')
            ->with('alertPolicy')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Service $service) use ($engine, &$count) {
                $engine->evaluate($service);
                $count++;
            });

        $this->info("Services evaluated: {$count}");
        return self::SUCCESS;
    }
}
