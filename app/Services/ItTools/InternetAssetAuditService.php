<?php

namespace App\Services\ItTools;

class InternetAssetAuditService
{
    public function __construct(
        private DomainAuditService $domain,
        private DnsAuditService $dns,
        private SslAuditService $ssl,
        private WebsiteAuditService $website,
        private IpAuditService $ip,
        private EmailSecurityAuditService $email,
    ) {}

    public function audit(string $domain, ?string $wanIp = null, array $dkimSelectors = []): array
    {
        $domain = strtolower(trim($domain));
        $domainResult = $this->domain->check($domain);
        $dnsResult = $this->dns->check($domain);
        $websiteResult = $this->website->check($domain);
        $sslResult = $this->ssl->check($domain);
        $emailResult = $this->email->check($domain, $dkimSelectors);

        $aRecords = collect(data_get($dnsResult, 'records.A', []))
            ->pluck('ip')->filter()->unique()->values()->all();
        $resolvedIp = $wanIp ?: ($aRecords[0] ?? null);

        return [
            'checked_at' => now()->toIso8601String(),
            'domain' => $domain,
            'domain_audit' => $domainResult,
            'dns_audit' => $dnsResult,
            'ssl_audit' => $sslResult,
            'website_audit' => $websiteResult,
            'email_audit' => $emailResult,
            'ip_audit' => $resolvedIp ? $this->ip->check($resolvedIp) : null,
            'wan_ip_supplied' => $wanIp,
            'resolved_ipv4' => $aRecords,
        ];
    }
}
