<?php

namespace App\Services\ItTools;

use Illuminate\Support\Facades\Http;

class InfrastructureDetectionService
{
    public function detect(string $domain, array $dns = [], array $website = [], ?array $ipAudit = null): array
    {
        $ns = collect(data_get($dns, 'records.NS', []))->pluck('target')->filter()->implode(' ');
        $cname = collect(data_get($dns, 'records.CNAME', []))->pluck('target')->filter()->implode(' ');
        $server = (string) data_get($website, 'https.server', '');
        $headers = (array) data_get($website, 'https.headers', []);

        $signals = strtolower(trim($ns . ' ' . $cname . ' ' . $server . ' ' . json_encode($headers) . ' ' . json_encode($ipAudit)));
        $cdn = $this->match($signals, [
            'Cloudflare' => ['cloudflare'],
            'Akamai' => ['akamai', 'edgekey.net', 'akamaized.net'],
            'Fastly' => ['fastly'],
            'AWS CloudFront' => ['cloudfront.net', 'cloudfront'],
            'Azure Front Door' => ['azurefd.net', 'frontdoor'],
            'Google Cloud CDN' => ['googleusercontent.com'],
        ]);
        $waf = $this->match($signals, [
            'Cloudflare WAF' => ['cloudflare'],
            'Akamai WAF' => ['akamai'],
            'AWS WAF/CloudFront' => ['cloudfront'],
            'Azure WAF' => ['azurefd.net', 'frontdoor'],
        ]);
        $dnsProvider = data_get($dns, 'dns_provider');
        $hosting = data_get($ipAudit, 'provider') ?: data_get($ipAudit, 'organization');

        return [
            'domain' => strtolower(trim($domain)),
            'dns_provider' => $dnsProvider,
            'cdn' => $cdn,
            'waf' => $waf,
            'hosting_provider' => $hosting,
            'detection_confidence' => $this->confidence($cdn, $waf, $hosting),
        ];
    }

    private function match(string $signals, array $rules): ?string
    {
        foreach ($rules as $name => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($signals, strtolower($needle))) {
                    return $name;
                }
            }
        }
        return null;
    }

    private function confidence(?string $cdn, ?string $waf, ?string $hosting): string
    {
        $hits = collect([$cdn, $waf, $hosting])->filter()->count();
        return match (true) {
            $hits >= 3 => 'high',
            $hits === 2 => 'medium',
            $hits === 1 => 'low',
            default => 'unknown',
        };
    }
}
