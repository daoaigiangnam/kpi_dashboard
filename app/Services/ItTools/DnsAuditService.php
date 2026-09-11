<?php

namespace App\Services\ItTools;

class DnsAuditService
{
    public function check(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $records = [];
        foreach ([DNS_A, DNS_AAAA, DNS_CNAME, DNS_NS, DNS_MX, DNS_TXT, DNS_SOA, DNS_CAA] as $type) {
            $records[$this->typeName($type)] = $this->normalize(@dns_get_record($domain, $type) ?: []);
        }

        $txt = collect($records['TXT'] ?? [])
            ->map(fn (array $row) => $row['txt'] ?? $row['value'] ?? null)
            ->filter()
            ->values();

        $result = [
            'domain' => $domain,
            'records' => $records,
            'spf' => $txt->first(fn ($v) => str_starts_with($v, 'v=spf1')),
            'dmarc' => $this->lookupTxt('_dmarc.' . $domain),
            'dnssec' => $this->lookupDnssec($domain),
        ];

        $mx = $records['MX'] ?? [];
        $result['email_provider'] = $this->inferMailProvider($mx);
        $result['dns_provider'] = $this->inferDnsProvider($records['NS'] ?? []);
        return $result;
    }

    private function lookupTxt(string $name): ?string
    {
        $records = @dns_get_record($name, DNS_TXT) ?: [];
        foreach ($records as $record) {
            $value = $record['txt'] ?? $record['value'] ?? null;
            if ($value) return $value;
        }
        return null;
    }

    private function lookupDnssec(string $domain): bool
    {
        if (defined('DNS_DNSKEY')) {
            return !empty(@dns_get_record($domain, DNS_DNSKEY));
        }
        return false;
    }

    private function inferMailProvider(array $mx): ?string
    {
        $hosts = collect($mx)->pluck('target')->implode(' ');
        foreach ([
            'Microsoft 365' => ['outlook.com', 'protection.outlook.com'],
            'Google Workspace' => ['google.com', 'googlemail.com'],
            'Zoho Mail' => ['zoho.com', 'zoho.eu'],
            'Proton Mail' => ['protonmail.ch', 'protonmail.com', 'proton.me'],
        ] as $name => $needles) {
            foreach ($needles as $needle) {
                if (stripos($hosts, $needle) !== false) return $name;
            }
        }
        return null;
    }

    private function inferDnsProvider(array $ns): ?string
    {
        $hosts = collect($ns)->pluck('target')->implode(' ');
        foreach ([
            'Cloudflare' => ['cloudflare.com'],
            'AWS Route 53' => ['awsdns-'],
            'Google Cloud DNS' => ['googledomains.com', 'google.com'],
            'Azure DNS' => ['azure-dns.com'],
            'Akamai Edge DNS' => ['akamaiedge.net'],
        ] as $name => $needles) {
            foreach ($needles as $needle) {
                if (stripos($hosts, $needle) !== false) return $name;
            }
        }
        return null;
    }

    private function normalize(array $items): array
    {
        return array_map(function (array $item) {
            return array_filter($item, fn ($v) => !is_array($v) && $v !== null);
        }, $items);
    }

    private function typeName(int $type): string
    {
        return match ($type) {
            DNS_A => 'A', DNS_AAAA => 'AAAA', DNS_CNAME => 'CNAME', DNS_NS => 'NS',
            DNS_MX => 'MX', DNS_TXT => 'TXT', DNS_SOA => 'SOA', DNS_CAA => 'CAA', default => (string) $type,
        };
    }
}
