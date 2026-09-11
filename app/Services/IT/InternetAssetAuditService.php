<?php

namespace App\Services\IT;

class InternetAssetAuditService
{
    public function __construct(
        private readonly DomainAuditService $domains,
        private readonly DnsAuditService $dns,
        private readonly SslAuditService $ssl,
        private readonly WebsiteAuditService $website,
        private readonly IpAuditService $ip,
    ) {
    }

    public function audit(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $started = microtime(true);

        return [
            'domain' => $domain,
            'checked_at' => now()->toIso8601String(),
            'response_ms' => (int) round((microtime(true) - $started) * 1000),
            'domain_audit' => $this->domains->audit($domain),
            'dns_audit' => $this->dns->audit($domain),
            'ssl_audit' => $this->ssl->audit($domain),
            'website_audit' => $this->website->audit($domain),
            'ip_audit' => $this->ip->audit($domain),
        ];
    }

    public function auditBatch(array $domains): array
    {
        $results = [];
        foreach ($domains as $domain) {
            $domain = trim((string) $domain);
            if ($domain !== '') {
                $results[] = $this->audit($domain);
            }
        }
        return $results;
    }
}
