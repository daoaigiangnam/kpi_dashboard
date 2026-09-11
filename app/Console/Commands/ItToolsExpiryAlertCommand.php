<?php

namespace App\Console\Commands;

use App\Models\ItToolAudit;
use App\Services\ItTools\ItToolStatusService;
use Illuminate\Console\Command;

class ItToolsExpiryAlertCommand extends Command
{
    protected $signature = 'it-tools:expiry-alert {--days=30 : Warning threshold in days}';
    protected $description = 'Report domains and SSL certificates that require attention.';

    public function handle(): int
    {
        $threshold = max(0, min((int) $this->option('days'), 365));
        $audits = ItToolAudit::query()->latest('id')->limit(5000)->get();
        $latest = $audits->unique('domain')->values();
        $count = 0;

        foreach ($latest as $audit) {
            $domainDays = data_get($audit->result, 'domain_audit.days_remaining');
            $sslDays = data_get($audit->result, 'ssl_audit.days_remaining');
            $domainLevel = ItToolStatusService::level(is_numeric($domainDays) ? (int) $domainDays : null);
            $sslLevel = ItToolStatusService::level(is_numeric($sslDays) ? (int) $sslDays : null);

            if (($domainDays !== null && (int) $domainDays <= $threshold) || ($sslDays !== null && (int) $sslDays <= $threshold)) {
                $this->line(sprintf(
                    '%s | Domain: %s (%s) | SSL: %s (%s)',
                    $audit->domain,
                    $domainDays ?? 'N/A',
                    $domainLevel,
                    $sslDays ?? 'N/A',
                    $sslLevel,
                ));
                $count++;
            }
        }

        $this->info("Assets requiring attention: {$count}");
        return self::SUCCESS;
    }
}
