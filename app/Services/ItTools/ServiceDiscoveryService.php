<?php

namespace App\Services\ItTools;

use Illuminate\Support\Facades\Http;

class ServiceDiscoveryService
{
    private const DEFAULT_HOSTS = [
        'www', 'api', 'app', 'portal', 'vpn', 'remote', 'mail', 'smtp', 'imap', 'pop',
        'autodiscover', 'autoconfig', 'webmail', 'owa', 'admin', 'dev', 'staging', 'test',
    ];

    public function discover(string $domain, array $hosts = []): array
    {
        $domain = strtolower(trim($domain));
        $labels = $this->sanitizeHosts($hosts ?: self::DEFAULT_HOSTS);
        $services = [];

        foreach ($labels as $label) {
            $host = $label . '.' . $domain;
            $ips = $this->resolve($host);
            $web = $this->checkWeb($host);
            $services[] = [
                'hostname' => $host,
                'label' => $label,
                'ips' => $ips,
                'dns' => !empty($ips),
                'http' => $web['http'],
                'https' => $web['https'],
                'status' => $web['https']['online'] ? 'online' : ($web['http']['online'] ? 'online' : (!empty($ips) ? 'dns_only' : 'not_found')),
            ];
        }

        return [
            'domain' => $domain,
            'checked_hosts' => count($labels),
            'services' => $services,
        ];
    }

    private function sanitizeHosts(array $hosts): array
    {
        $limit = request()->header('X-IT-Bulk-Audit') ? 6 : 50;
        return collect($hosts)
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter(fn ($value) => preg_match('/^[a-z0-9][a-z0-9.-]{0,62}$/', $value))
            ->unique()->take($limit)->values()->all();
    }

    private function resolve(string $host): array
    {
        $ips = [];
        foreach ([DNS_A, DNS_AAAA] as $type) {
            foreach (@dns_get_record($host, $type) ?: [] as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if ($ip) $ips[] = $ip;
            }
        }
        return array_values(array_unique($ips));
    }

    private function checkWeb(string $host): array
    {
        $result = [];
        foreach (['http', 'https'] as $scheme) {
            $started = microtime(true);
            try {
                $response = Http::timeout(3)->withOptions(['allow_redirects' => ['track_redirects' => true]])->get($scheme . '://' . $host);
                $status = $response->status();
                $result[$scheme] = [
                    'status' => $status,
                    'online' => $status >= 100 && $status <= 599,
                    'final_url' => $response->effectiveUri()?->__toString(),
                    'response_time_ms' => round((microtime(true) - $started) * 1000, 1),
                    'server' => $response->header('Server'),
                ];
            } catch (\Throwable $e) {
                $result[$scheme] = [
                    'status' => null,
                    'online' => false,
                    'final_url' => null,
                    'response_time_ms' => round((microtime(true) - $started) * 1000, 1),
                    'server' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }
        return $result;
    }
}