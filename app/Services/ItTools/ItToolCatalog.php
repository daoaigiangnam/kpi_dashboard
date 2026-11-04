<?php

namespace App\Services\ItTools;

class ItToolCatalog
{
    public static function all(): array
    {
        return [
            ['code' => 'asset_audit', 'name' => 'Internet Asset Audit', 'description' => 'Domain, DNS, SSL/TLS, Website, Email and IP audit.', 'permission' => 'it_tools.audit'],
            ['code' => 'domain', 'name' => 'Domain Audit', 'description' => 'RDAP, registrar, expiry, status and nameserver checks.', 'permission' => 'it_tools.domain'],
            ['code' => 'dns', 'name' => 'DNS Lookup', 'description' => 'A/AAAA/CNAME/NS/MX/TXT/SOA/CAA, SPF, DMARC and DNS provider.', 'permission' => 'it_tools.dns'],
            ['code' => 'ssl', 'name' => 'SSL/TLS Checker', 'description' => 'Certificate issuer, vendor, validity, SAN and hostname verification.', 'permission' => 'it_tools.ssl'],
            ['code' => 'website', 'name' => 'Website Checker', 'description' => 'HTTP/HTTPS status, redirects, response time and common security headers.', 'permission' => 'it_tools.website'],
            ['code' => 'email', 'name' => 'Email Security', 'description' => 'MX provider, SPF, DKIM, DMARC, MTA-STS and TLS-RPT.', 'permission' => 'it_tools.email'],
            ['code' => 'ip', 'name' => 'IP / Hosting Lookup', 'description' => 'IPv4/IPv6, PTR, ASN, organization and provider inference.', 'permission' => 'it_tools.ip'],
        ];
    }
}
