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
        private ServiceDiscoveryService $services,
        private ProviderDetectionService $providers,
    ) {}

    public function audit(string $domain, ?string $wanIp = null, array $dkimSelectors = [], array $serviceHosts = []): array
    {
        $domain = strtolower(trim($domain));
        $domainResult = $this->domain->check($domain);
        $dnsResult = $this->dns->check($domain);
        $websiteResult = $this->website->check($domain);
        $sslResult = $this->ssl->check($domain);
        $emailResult = $this->email->check($domain, $dkimSelectors);
        $serviceResult = $this->services->discover($domain, $serviceHosts);

        $aRecords = collect(data_get($dnsResult, 'records.A', []))
            ->pluck('ip')->filter()->unique()->values()->all();
        $resolvedIp = $wanIp ?: ($aRecords[0] ?? null);
        $ipResult = $resolvedIp ? $this->ip->check($resolvedIp) : null;
        $providerResult = $this->providers->detect($domain, $dnsResult, $websiteResult, $ipResult);

        return [
            'checked_at' => now()->toIso8601String(),
            'domain' => $domain,
            'domain_audit' => $domainResult,
            'dns_audit' => $dnsResult,
            'ssl_audit' => $sslResult,
            'website_audit' => $websiteResult,
            'email_audit' => $emailResult,
            'service_discovery' => $serviceResult,
            'provider_detection' => $providerResult,
            'ip_audit' => $ipResult,
            'wan_ip_supplied' => $wanIp,
            'resolved_ipv4' => $aRecords,
        ];
    }
}
