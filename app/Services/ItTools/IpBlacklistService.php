<?php

namespace App\Services\ItTools;

class IpBlacklistService
{
    /**
     * DNS-based blacklist (DNSBL) providers.
     * A listed IP normally returns an A record; NXDOMAIN means not listed.
     */
    private array $lists = [
        ['name' => 'Spamhaus ZEN', 'zone' => 'zen.spamhaus.org'],
        ['name' => 'Barracuda Reputation Block List', 'zone' => 'b.barracudacentral.org'],
        ['name' => 'SpamCop Blocking List', 'zone' => 'bl.spamcop.net'],
    ];

    public function check(string $ip): array
    {
        $ip = trim($ip);

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new \InvalidArgumentException('Please enter a valid public IPv4 address.');
        }

        if ($this->isPrivateOrReserved($ip)) {
            throw new \InvalidArgumentException('Blacklist checking requires a public IPv4 address. Private/reserved IPs cannot be checked meaningfully.');
        }

        $queryIp = implode('.', array_reverse(explode('.', $ip)));
        $results = [];

        foreach ($this->lists as $list) {
            $host = $queryIp . '.' . $list['zone'];
            $listed = false;
            $response = null;
            $error = null;

            try {
                $records = @dns_get_record($host, DNS_A);
                if ($records === false) {
                    $error = 'DNS query failed';
                } elseif (count($records) > 0) {
                    $listed = true;
                    $response = collect($records)->pluck('ip')->filter()->values()->all();
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }

            $results[] = [
                'name' => $list['name'],
                'zone' => $list['zone'],
                'listed' => $listed,
                'status' => $error ? 'error' : ($listed ? 'listed' : 'clean'),
                'response' => $response,
                'error' => $error,
            ];
        }

        $checked = count($results);
        $listedCount = collect($results)->where('listed', true)->count();
        $errorCount = collect($results)->where('status', 'error')->count();

        return [
            'ip' => $ip,
            'checked_at' => now()->toIso8601String(),
            'summary' => [
                'checked' => $checked,
                'listed' => $listedCount,
                'clean' => $checked - $listedCount - $errorCount,
                'errors' => $errorCount,
                'overall' => $listedCount > 0 ? 'listed' : ($errorCount > 0 ? 'partial' : 'clean'),
            ],
            'results' => $results,
            'note' => 'DNSBL results are provider-specific and can change. A clean result does not guarantee that an IP has no reputation issues on every blacklist.',
        ];
    }

    private function isPrivateOrReserved(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
