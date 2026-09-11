<?php

namespace App\Services\ItTools;

class EmailSecurityAuditService
{
    public function check(string $domain, array $dkimSelectors = []): array
    {
        $domain = strtolower(trim($domain));
        $txt = fn (string $host): array => dns_get_record($host, DNS_TXT) ?: [];

        $mx = dns_get_record($domain, DNS_MX) ?: [];
        $domainTxt = $txt($domain);
        $dmarcTxt = $txt('_dmarc.' . $domain);
        $mtaStsTxt = $txt('_mta-sts.' . $domain);
        $tlsRptTxt = $txt('_smtp._tls.' . $domain);

        $dkim = [];
        foreach ($dkimSelectors as $selector) {
            $selector = preg_replace('/[^a-z0-9._-]/i', '', (string) $selector);
            if ($selector === '') {
                continue;
            }
            $records = $txt($selector . '._domainkey.' . $domain);
            $dkim[$selector] = [
                'present' => !empty($records),
                'records' => $records,
            ];
        }

        $spf = collect($domainTxt)->pluck('txt')->merge(collect($domainTxt)->pluck('value'))
            ->filter(fn ($v) => is_string($v) && str_starts_with(strtolower($v), 'v=spf1'))
            ->first();

        $dmarc = collect($dmarcTxt)->pluck('txt')->merge(collect($dmarcTxt)->pluck('value'))->filter()->first();
        $mtaSts = collect($mtaStsTxt)->pluck('txt')->merge(collect($mtaStsTxt)->pluck('value'))->filter()->first();
        $tlsRpt = collect($tlsRptTxt)->pluck('txt')->merge(collect($tlsRptTxt)->pluck('value'))->filter()->first();

        return [
            'domain' => $domain,
            'mx' => $mx,
            'provider' => $this->inferProvider($mx),
            'spf' => $spf,
            'spf_present' => $spf !== null,
            'dmarc' => $dmarc,
            'dmarc_present' => $dmarc !== null,
            'dkim' => $dkim,
            'mta_sts' => $mtaSts,
            'mta_sts_present' => $mtaSts !== null,
            'tls_rpt' => $tlsRpt,
            'tls_rpt_present' => $tlsRpt !== null,
        ];
    }

    private function inferProvider(array $mx): ?string
    {
        $targets = collect($mx)->pluck('target')->implode(' ');
        foreach ([
            'Microsoft 365' => ['outlook.com', 'protection.outlook.com'],
            'Google Workspace' => ['google.com', 'googlemail.com'],
            'Zoho Mail' => ['zoho.com'],
            'Proton Mail' => ['protonmail.ch', 'proton.me'],
            'Fastmail' => ['messagingengine.com'],
            'Amazon SES' => ['amazonses.com'],
        ] as $name => $needles) {
            foreach ($needles as $needle) {
                if (stripos($targets, $needle) !== false) {
                    return $name;
                }
            }
        }
        return $targets !== '' ? $targets : null;
    }
}
