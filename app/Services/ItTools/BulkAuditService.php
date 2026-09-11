<?php

namespace App\Services\ItTools;

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
            $domain = is_array($item) ? ($item['domain'] ?? '') : (string) $item;
            $wanIp = is_array($item) ? ($item['wan_ip'] ?? null) : null;
            $domain = trim((string) $domain);

            if ($domain === '') {
                $results[] = [
                    'status' => 'error',
                    'domain' => null,
                    'wan_ip' => $wanIp,
                    'error' => 'Domain is required.',
                ];
                continue;
            }

            if (!filter_var($wanIp, FILTER_VALIDATE_IP) && $wanIp !== null && $wanIp !== '') {
                $results[] = [
                    'status' => 'error',
                    'domain' => $domain,
                    'wan_ip' => $wanIp,
                    'error' => 'Invalid WAN IP address.',
                ];
                continue;
            }

            $results[] = [
                'status' => 'ok',
                'domain' => $domain,
                'wan_ip' => $wanIp ?: null,
                'audit' => $this->audit->audit($domain, $wanIp ?: null),
            ];
        }

        return [
            'requested' => count($items),
            'processed' => count($results),
            'max_items' => $maxItems,
            'results' => $results,
        ];
    }
}
