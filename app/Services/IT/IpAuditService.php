<?php

namespace App\Services\IT;

class IpAuditService
{
    public function audit(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = [];
        foreach ($records as $record) {
            foreach (['ip', 'ipv6'] as $key) {
                if (!empty($record[$key])) {
                    $ips[] = $record[$key];
                }
            }
        }
        $ips = array_values(array_unique($ips));

        $items = [];
        foreach ($ips as $ip) {
            $items[] = [
                'ip' => $ip,
                'version' => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 6 : 4,
                'ptr' => @gethostbyaddr($ip) ?: null,
            ];
        }

        return [
            'status' => true,
            'host' => $host,
            'ips' => $items,
        ];
    }
}
