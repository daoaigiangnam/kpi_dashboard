<?php

namespace App\Services\ItTools;

use App\Models\ItToolAudit;

class BulkAuditService
{
    public function __construct(private InternetAssetAuditService $audit)
    {
    }

    public function audit(array $items, int $maxItems = 100): array
    {
        $items = array_slice($items, 0, $maxItems);
        $results = [];

        foreach ($items as $item) {
            $domain = is_array($item) ? trim((string) ($item['domain'] ?? '')) : trim((string) $item);
            $wanIp = is_array($item) ? ($item['wan_ip'] ?? null) : null;
            $dkimSelectors = is_array($item) && isset($item['dkim_selectors']) && is_array($item['dkim_selectors'])
                ? array_values(array_unique(array_slice($item['dkim_selectors'], 0, 20)))
                : [];
            $started = microtime(true);

            if ($domain === '') {
                $results[] = ['status' => 'error', 'domain' => null, 'wan_ip' => $wanIp, 'error' => 'Domain is required.'];
                continue;
            }

            if ($wanIp !== null && $wanIp !== '' && filter_var($wanIp, FILTER_VALIDATE_IP) === false) {
                $results[] = ['status' => 'error', 'domain' => $domain, 'wan_ip' => $wanIp, 'error' => 'Invalid WAN IP address.'];
                continue;
            }

            try {
                $auditResult = $this->audit->audit($domain, $wanIp ?: null, $dkimSelectors);
                ItToolAudit::create([
                    'user_id' => auth()->id(),
                    'domain' => $domain,
                    'wan_ip' => $wanIp ?: null,
                    'status' => 'completed',
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    'result' => $auditResult,
                ]);
                $results[] = ['status' => 'ok', 'domain' => $domain, 'wan_ip' => $wanIp ?: null, 'audit' => $auditResult];
            } catch (\Throwable $e) {
                ItToolAudit::create([
                    'user_id' => auth()->id(),
                    'domain' => $domain,
                    'wan_ip' => $wanIp ?: null,
                    'status' => 'error',
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    'error' => $e->getMessage(),
                ]);
                $results[] = ['status' => 'error', 'domain' => $domain, 'wan_ip' => $wanIp ?: null, 'error' => $e->getMessage()];
            }
        }

        return ['requested' => count($items), 'processed' => count($results), 'max_items' => $maxItems, 'results' => $results];
    }
}
