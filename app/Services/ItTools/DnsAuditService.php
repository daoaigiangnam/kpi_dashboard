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

        $ns = $records['NS'] ?? [];
        $dnsProvider = $this->inferDnsProvider($ns);

        $result = [
            'domain' => $domain,
            'records' => $records,
            'spf' => $txt->first(fn ($v) => str_starts_with(strtolower($v), 'v=spf1')),
            'dmarc' => $this->lookupTxt('_dmarc.' . $domain),
            'dnssec' => $this->lookupDnssec($domain),
            'email_provider' => $this->inferMailProvider($records['MX'] ?? []),
            'dns_provider' => $dnsProvider,
            'dns_provider_source' => $dnsProvider ? 'NS' : null,
            'dns_nameservers' => collect($ns)->pluck('target')->filter()->map('strtolower')->unique()->values()->all(),
        ];

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
        $normalized = strtolower($hosts);

        $providers = [
            'Cloudflare' => ['cloudflare.com'],
            'AWS Route 53' => ['awsdns-', 'awsdns-'],
            'Google Cloud DNS' => ['googledomains.com', 'google.com'],
            'Azure DNS' => ['azure-dns.com'],
            'Akamai Edge DNS' => ['akamaiedge.net', 'akam.net'],
            'DigitalOcean DNS' => ['digitalocean.com'],
            'Vultr DNS' => ['vultr.com', 'vultr-dns.com'],
            'DNSPod' => ['dnspod.net', 'dnspod.com'],
            'Namecheap DNS' => ['registrar-servers.com'],
            'GoDaddy DNS' => ['domaincontrol.com'],
            'Name.com DNS' => ['name-services.com'],
            'NS1' => ['nsone.net'],
            'Bunny DNS' => ['bunny.net'],
            'Hurricane Electric DNS' => ['he.net'],
        ];

        foreach ($providers as $name => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($normalized, strtolower($needle))) return $name;
            }
        }

        // If the NS hostnames do not match a known managed DNS provider,
        // return the authoritative nameserver host instead of claiming
        // that the provider is known. This gives IT Support a useful
        // investigation lead while keeping the provider classification honest.
        $firstNs = collect($ns)->pluck('target')->filter()->map(fn ($v) => rtrim(strtolower($v), '.'))->first();
        return $firstNs ? 'Authoritative DNS: ' . $firstNs : null;
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
