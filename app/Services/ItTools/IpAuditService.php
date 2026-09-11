<?php

namespace App\Services\ItTools;

use Illuminate\Support\Facades\Http;

class IpAuditService
{
    public function check(string $ip): array
    {
        $ip = trim($ip);
        $result = [
            'ip' => $ip, 'valid' => filter_var($ip, FILTER_VALIDATE_IP) !== false,
            'ptr' => null, 'asn' => null, 'network' => null, 'organization' => null,
            'country' => null, 'provider' => null, 'error' => null,
        ];
        if (!$result['valid']) { $result['error'] = 'Invalid IP address.'; return $result; }
        $result['ptr'] = gethostbyaddr($ip) ?: null;

        try {
            $response = Http::timeout(10)->acceptJson()->get('https://rdap.org/ip/' . rawurlencode($ip));
            if ($response->successful()) {
                $data = $response->json();
                $result['network'] = $data['name'] ?? null;
                $result['country'] = $data['country'] ?? null;
                $entity = collect($data['entities'] ?? [])->first();
                $result['organization'] = data_get($entity, 'vcardArray.1.1.3.0');
                foreach (($data['entities'] ?? []) as $e) {
                    $role = collect($e['roles'] ?? [])->first();
                    if ($role === 'registrant' || $role === 'administrative') {
                        $result['organization'] = data_get($e, 'vcardArray.1.1.3.0') ?: $result['organization'];
                    }
                }
                $result['asn'] = $data['handle'] ?? null;
                $result['provider'] = $result['organization'];
            }
        } catch (\Throwable $e) { $result['error'] = $e->getMessage(); }
        return $result;
    }
}
