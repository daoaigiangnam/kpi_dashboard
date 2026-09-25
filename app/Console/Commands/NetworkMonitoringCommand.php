<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\ItTools\NetworkMonitoringService;
use Illuminate\Console\Command;

class NetworkMonitoringCommand extends Command
{
    protected $signature = 'services:monitor-network {--limit=500 : Maximum monitored services per run}';
    protected $description = 'Check configured Internet/FTTH/VPS/Website services by PING or TCP port(s).';

    public function handle(NetworkMonitoringService $monitor): int
    {
        $limit = max(1, min((int) $this->option('limit'), 5000));
        $checked = 0; $online = 0; $offline = 0; $skipped = 0;

        Service::query()
            ->where('status', 'active')
            ->whereIn('monitor_check_method', ['ping', 'port'])
            ->whereNotNull('monitor_target')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Service $service) use ($monitor, &$checked, &$online, &$offline, &$skipped) {
                $result = $monitor->monitor($service);
                if (!$result['checked']) { $skipped++; return; }
                $checked++;
                $result['online'] ? $online++ : $offline++;
            });

        $this->info("Network monitoring: checked={$checked}; online={$online}; offline={$offline}; skipped={$skipped}");
        return self::SUCCESS;
    }
}
