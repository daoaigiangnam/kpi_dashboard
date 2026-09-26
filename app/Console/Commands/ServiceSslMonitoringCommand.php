<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\ItTools\ServiceAlertEmailService;
use App\Services\ItTools\ServiceAlertEngine;
use App\Services\ItTools\SslAuditService;
use Illuminate\Console\Command;

class ServiceSslMonitoringCommand extends Command
{
    protected $signature = 'services:monitor-ssl {--limit=500 : Maximum websites to check per run}';
    protected $description = 'Automatically check Website SSL certificates and create expiry alerts.';

    public function handle(SslAuditService $ssl, ServiceAlertEngine $engine, ServiceAlertEmailService $emailService): int
    {
        $limit = max(1, min((int) $this->option('limit'), 5000));
        $checked = 0;
        $failed = 0;
        $alerts = 0;
        $dueBefore = now()->subDay();

        Service::query()
            ->where('status', 'active')
            ->whereNotNull('monitor_target')
            ->where(function ($q) {
                $q->where('monitor_ports', 'like', '%443%')
                    ->orWhere('monitor_port', 443);
            })
            ->where(function ($q) use ($dueBefore) {
                $q->whereNull('ssl_last_checked_at')
                    ->orWhere('ssl_last_checked_at', '<=', $dueBefore);
            })
            ->with('alertPolicy')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Service $service) use ($ssl, $engine, $emailService, &$checked, &$failed, &$alerts) {
                $host = trim((string) $service->monitor_target);
                if ($host === '') return;

                $result = $ssl->check($host, 443);
                $checked++;

                if (($result['status'] ?? 'error') !== 'ok' || empty($result['valid_to'])) {
                    $failed++;
                    $service->forceFill([
                        'ssl_detected' => false,
                        'ssl_status' => 'error',
                        'ssl_last_checked_at' => now(),
                    ])->saveQuietly();
                    return;
                }

                $service->forceFill([
                    'ssl_detected' => true,
                    'ssl_valid_from_date' => $result['valid_from'] ? substr($result['valid_from'], 0, 10) : null,
                    'ssl_expiry_date' => substr($result['valid_to'], 0, 10),
                    'ssl_issuer' => $result['issuer'],
                    'ssl_status' => ($result['verify'] ?? false) ? 'valid' : 'hostname_mismatch',
                    'ssl_last_checked_at' => now(),
                ])->saveQuietly();

                if ($service->alert_policy_id) {
                    $event = $engine->evaluateSsl($service->fresh('alertPolicy'));
                    if ($event) {
                        $emailService->notifyNewAlert($event);
                        $alerts++;
                    }
                }
            });

        $this->info("SSL monitoring: checked={$checked}; failed={$failed}; alerts={$alerts}");
        return self::SUCCESS;
    }
}
