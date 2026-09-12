<?php

namespace App\Services\ItTools;

class DnsAuditService
{
    public function check(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $records = [];
        $types = [
            'A' => DNS_A,
            'AAAA' => DNS_AAAA,
            'CNAME' => DNS_CNAME,
            'NS' => DNS_NS,
            'MX' => DNS_MX,
            'TXT' => DNS_TXT,
            'SOA' => DNS_SOA,
            'CAA' => DNS_CAA,
        ];

        foreach ([
            'HINFO' => 'DNS_HINFO',
            'MINFO' => 'DNS_MINFO',
            'SRV' => 'DNS_SRV',
            'NAPTR' => 'DNS_NAPTR',
            'SSHFP' => 'DNS_SSHFP',
            'TLSA' => 'DNS_TLSA',
            'SVCB' => 'DNS_SVCB',
            'HTTPS' => 'DNS_HTTPS',
            'RP' => 'DNS_RP',
            'LOC' => 'DNS_LOC',
            'DS' => 'DNS_DS',
            'DNSKEY' => 'DNS_DNSKEY',
        ] as $name => $constant) {
            if (defined($constant)) {
                $types[$name] = constant($constant);
            }
        }

        foreach ($types as $name => $type) {
            $records[$name] = $this->normalize(@dns_get_record($domain, $type) ?: []);
        }

        $txt = collect($records['TXT'] ?? [])
            ->map(fn (array $row) => $row['txt'] ?? $row['value'] ?? null)
            ->filter()
            ->values();

        $ns = $records['NS'] ?? [];
        $dnsProvider = $this->inferDnsProvider($ns, $records['SOA'] ?? []);
        $dnssec = !empty($records['DS'] ?? []) || !empty($records['DNSKEY'] ?? []);

        return [
            'domain' => $domain,
            'records' => $records,
            'record_types_checked' => array_keys($types),
            'spf' => $txt->first(fn ($v) => str_starts_with(strtolower($v), 'v=spf1')),
            'dmarc' => $this->lookupTxt('_dmarc.' . $domain),
            'dnssec' => $dnssec,
            'email_provider' => $this->inferMailProvider($records['MX'] ?? []),
            'dns_provider' => $dnsProvider,
            'dns_provider_source' => $dnsProvider ? 'NS/SOA' : null,
            'dns_nameservers' => collect($ns)->pluck('target')->filter()->map(fn ($v) => rtrim(strtolower($v), '.'))->unique()->values()->all(),
        ];
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

    private function inferDnsProvider(array $ns, array $soa): ?string
    {
        $nsHosts = collect($ns)->pluck('target')->filter()->map(fn ($v) => rtrim(strtolower($v), '.'));
        $soaHosts = collect($soa)->flatMap(fn ($row) => [
            $row['mname'] ?? null,
            $row['rname'] ?? null,
        ])->filter()->map(fn ($v) => rtrim(strtolower($v), '.'));
        $normalized = $nsHosts->merge($soaHosts)->implode(' ');

        $providers = [
            'P.A. Viet Nam / DOTVNDNS' => ['dotvndns.vn', 'dotvndns.com', 'pavietnam.vn', 'pavietnam.com'],
            'MATBAO' => ['matbao.vn', 'matbao.com'],
            'Cloudflare' => ['cloudflare.com'],
            'AWS Route 53' => ['awsdns-'],
            'Google Cloud DNS' => ['googledomains.com'],
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

        $firstNs = $nsHosts->first();
        return $firstNs ? 'Authoritative DNS: ' . $firstNs : null;
    }

    private function normalize(array $items): array
    {
        return array_map(function (array $item) {
            return array_filter($item, fn ($v) => !is_array($v) && $v !== null);
        }, $items);
    }
}
