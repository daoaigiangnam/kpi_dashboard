<?php

namespace App\Services\IT;

class DnsAuditService
{
    public function audit(string $host): array
    {
        $types = [
            'A' => DNS_A,
            'AAAA' => DNS_AAAA,
            'CNAME' => DNS_CNAME,
            'NS' => DNS_NS,
            'MX' => DNS_MX,
            'TXT' => DNS_TXT,
            'SOA' => DNS_SOA,
            'CAA' => defined('DNS_CAA') ? DNS_CAA : 0,
        ];

        $result = [];
        foreach ($types as $name => $type) {
            if ($type === 0) {
                $result[$name] = [];
                continue;
            }
            $records = @dns_get_record($host, $type);
            $result[$name] = $records ?: [];
        }

        $txt = $result['TXT'] ?? [];
        $result['SPF'] = array_values(array_filter($txt, static fn (array $r): bool => str_starts_with($r['txt'] ?? '', 'v=spf1')));
        $dmarcHost = '_dmarc.' . $host;
        $dmarcRecords = @dns_get_record($dmarcHost, DNS_TXT) ?: [];
        $result['DMARC'] = array_values(array_filter($dmarcRecords, static fn (array $r): bool => str_starts_with($r['txt'] ?? '', 'v=DMARC1')));
        $result['DNSSEC'] = $this->dnssec($host);

        return [
            'status' => true,
            'host' => $host,
            'records' => $result,
        ];
    }

    private function dnssec(string $host): bool
    {
        if (!defined('DNSKEY')) {
            return false;
        }
        return !empty(@dns_get_record($host, DNSKEY));
    }
}
