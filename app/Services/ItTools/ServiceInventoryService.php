<?php

namespace App\Services\ItTools;

class ServiceInventoryService
{
    public function discover(string $domain, array $customHosts = []): array
    {
        $domain = strtolower(trim($domain));
        $labels = array_values(array_unique(array_merge([
            'www','api','app','portal','vpn','remote','mail','smtp','imap','pop','webmail','autodiscover','owa','admin','dev','staging','test',
        ], array_map(fn ($v) => trim(strtolower((string) $v)), $customHosts))));

        $items = [];
        foreach ($labels as $label) {
            if ($label === '') continue;
            $host = $label . '.' . $domain;
            $a = @dns_get_record($host, DNS_A) ?: [];
            $aaaa = @dns_get_record($host, DNS_AAAA) ?: [];
            $ips = array_values(array_unique(array_merge(
                array_values(array_filter(array_column($a, 'ip'))),
                array_values(array_filter(array_column($aaaa, 'ipv6'))),
            )));
            $items[] = [
                'label' => $label,
                'hostname' => $host,
                'status' => !empty($ips) ? 'dns_only' : 'not_found',
                'ipv4' => array_values(array_filter(array_column($a, 'ip'))),
                'ipv6' => array_values(array_filter(array_column($aaaa, 'ipv6'))),
                'http' => $this->probe($host, 'http'),
                'https' => $this->probe($host, 'https'),
            ];
            if (!empty($ips) && (($items[array_key_last($items)]['http']['online'] ?? false) || ($items[array_key_last($items)]['https']['online'] ?? false))) {
                $items[array_key_last($items)]['status'] = 'online';
            }
        }

        return [
            'domain' => $domain,
            'count' => count($items),
            'online' => count(array_filter($items, fn ($item) => $item['status'] === 'online')),
            'dns_only' => count(array_filter($items, fn ($item) => $item['status'] === 'dns_only')),
            'not_found' => count(array_filter($items, fn ($item) => $item['status'] === 'not_found')),
            'items' => $items,
        ];
    }

    private function probe(string $host, string $scheme): array
    {
        $url = $scheme . '://' . $host;
        $started = microtime(true);
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 4,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 7,
                CURLOPT_USERAGENT => 'KPI-Dashboard-ITTools/1.0',
                CURLOPT_NOBODY => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: null;
            $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: null;
            curl_close($ch);
            return [
                'status' => $status,
                'online' => $status !== null && $status > 0,
                'response_time_ms' => round((microtime(true) - $started) * 1000, 1),
                'final_url' => $final,
                'error' => $error ?: null,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => null,
                'online' => false,
                'response_time_ms' => round((microtime(true) - $started) * 1000, 1),
                'final_url' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
