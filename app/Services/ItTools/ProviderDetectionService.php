<?php

namespace App\Services\ItTools;

use Illuminate\Support\Facades\Http;

class ProviderDetectionService
{
    public function detect(string $domain, array $dns = [], array $website = [], ?array $ipAudit = null): array
    {
        $signals = [];
        $ns = collect(data_get($dns, 'records.NS', []))->pluck('target')->map('strtolower')->all();
        $a = collect(data_get($dns, 'records.A', []))->pluck('ip')->filter()->values()->all();
        $headers = collect($website)->flatten(1);
        $server = collect($website)->pluck('server')->filter()->implode(' ');
        $finalUrls = collect($website)->pluck('final_url')->filter()->implode(' ');

        $provider = $this->match($ns, $a, $server, $finalUrls);
        $cdn = $provider;
        $waf = $this->detectWaf($website, $ns, $server);

        return [
            'domain' => strtolower(trim($domain)),
            'dns_provider' => $this->dnsProvider($ns),
            'cdn' => $cdn,
            'waf' => $waf,
            'hosting_provider' => $ipAudit['provider'] ?? ($ipAudit['organization'] ?? null),
            'signals' => [
                'nameservers' => $ns,
                'a_records' => $a,
                'server_headers' => $server,
                'final_urls' => $finalUrls,
                'ip_provider' => $ipAudit['provider'] ?? null,
            ],
        ];
    }

    private function dnsProvider(array $ns): ?string
    {
        $joined = implode(' ', $ns);
        foreach ([
            'Cloudflare' => ['cloudflare.com'],
            'AWS Route 53' => ['awsdns-'],
            'Google Cloud DNS' => ['googledomains.com', 'googleusercontent.com'],
            'Azure DNS' => ['azure-dns.com'],
            'Akamai Edge DNS' => ['akamaiedge.net', 'akam.net'],
            'Fastly' => ['fastly.net'],
        ] as $name => $needles) {
            foreach ($needles as $needle) if (stripos($joined, $needle) !== false) return $name;
        }
        return null;
    }

    private function match(array $ns, array $ips, string $server, string $finalUrls): ?string
    {
        $signal = implode(' ', $ns) . ' ' . $server . ' ' . $finalUrls;
        foreach ([
            'Cloudflare' => ['cloudflare'],
            'Akamai' => ['akamai'],
            'Fastly' => ['fastly'],
            'AWS CloudFront' => ['cloudfront.net'],
            'Azure Front Door' => ['azurefd.net'],
            'Google Cloud CDN' => ['googleusercontent.com'],
        ] as $name => $needles) {
            foreach ($needles as $needle) if (stripos($signal, $needle) !== false) return $name;
        }
        return null;
    }

    private function detectWaf(array $website, array $ns, string $server): ?string
    {
        $signals = strtolower(implode(' ', $ns) . ' ' . $server);
        foreach ([
            'Cloudflare' => ['cf-ray', 'cloudflare'],
            'Akamai' => ['akamai'],
            'AWS WAF' => ['aws', 'awselb'],
            'Fastly' => ['fastly'],
        ] as $name => $needles) {
            foreach ($needles as $needle) if (str_contains($signals, $needle)) return $name;
        }
        foreach ($website as $entry) {
            if (is_array($entry) && !empty($entry['headers'])) {
                $h = strtolower(json_encode($entry['headers']));
                foreach (['Cloudflare' => ['cf-ray'], 'Akamai' => ['akamai'], 'Fastly' => ['fastly']] as $name => $needles) {
                    foreach ($needles as $needle) if (str_contains($h, $needle)) return $name;
                }
            }
        }
        return null;
    }
}
