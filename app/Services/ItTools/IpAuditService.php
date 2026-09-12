<?php

namespace App\Services\ItTools;

use Illuminate\Support\Facades\Http;

class IpAuditService
{
    public function check(string $ip): array
    {
        $ip = trim($ip);
        $result = [
            'ip' => $ip,
            'valid' => filter_var($ip, FILTER_VALIDATE_IP) !== false,
            'ptr' => null,
            'asn' => null,
            'network' => null,
            'organization' => null,
            'country' => null,
            'provider' => null,
            'source' => null,
            'error' => null,
        ];

        if (!$result['valid']) {
            $result['error'] = 'Invalid IP address.';
            return $result;
        }

        $ptr = @gethostbyaddr($ip);
        $result['ptr'] = $ptr && $ptr !== $ip ? $ptr : null;

        // First try RDAP. A public IP can belong to APNIC/ARIN/RIPE/LACNIC/AFRINIC,
        // so do not assume that rdap.org can resolve every address directly.
        foreach ([
            'https://rdap.apnic.net/ip/',
            'https://rdap.arin.net/registry/ip/',
            'https://rdap.db.ripe.net/ip/',
            'https://rdap.lacnic.net/rdap/ip/',
            'https://rdap.afrinic.net/rdap/ip/',
        ] as $base) {
            try {
                $response = Http::timeout(4)->acceptJson()->get($base . rawurlencode($ip));
                if (!$response->successful()) continue;
                $data = $response->json();
                $result['network'] = $data['name'] ?? ($data['handle'] ?? null);
                $result['country'] = $data['country'] ?? null;
                $result['organization'] = $this->entityName($data['entities'] ?? []);
                $result['asn'] = $data['handle'] ?? null;
                $result['provider'] = $result['organization'];
                $result['source'] = 'RDAP';
                return $result;
            } catch (\Throwable) {
                // Try the next registry/fallback source.
            }
        }

        // Lightweight public fallback. This is intentionally only used when RDAP
        // cannot answer; it supplies ASN/org/ISP data without requiring an API key.
        try {
            $response = Http::timeout(5)->acceptJson()->get('https://ipapi.co/' . rawurlencode($ip) . '/json/');
            if ($response->successful()) {
                $data = $response->json();
                $result['asn'] = $data['asn'] ?? null;
                $result['network'] = $data['network'] ?? null;
                $result['organization'] = $data['org'] ?? null;
                $result['provider'] = $data['org'] ?? ($data['asn'] ?? null);
                $result['country'] = $data['country_code'] ?? null;
                $result['source'] = 'ipapi.co';
                return $result;
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function entityName(array $entities): ?string
    {
        foreach ($entities as $entity) {
            $vcard = $entity['vcardArray'][1] ?? [];
            foreach ($vcard as $field) {
                if (!is_array($field) || count($field) < 4) continue;
                if (in_array($field[0], ['fn', 'org'], true)) {
                    $value = is_array($field[3]) ? ($field[3][0] ?? null) : $field[3];
                    if ($value) return trim((string) $value);
                }
            }
        }
        return null;
    }
}
