<?php

namespace App\Services\ItTools;

use Illuminate\Support\Facades\Http;

class DomainAuditService
{
    public function check(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = trim(explode('/', $domain)[0]);

        $result = [
            'domain' => $domain,
            'status' => 'error',
            'registrar' => null,
            'created_at' => null,
            'expires_at' => null,
            'days_remaining' => null,
            'statuses' => [],
            'nameservers' => [],
            'source' => null,
            'error' => null,
        ];

        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain)) {
            $result['error'] = 'Invalid domain name.';
            return $result;
        }

        try {
            $tld = strtolower(substr(strrchr($domain, '.'), 1));
            $rdapBase = match ($tld) {
                'com' => 'https://rdap.verisign.com/com/v1/domain/',
                'net' => 'https://rdap.verisign.com/net/v1/domain/',
                default => 'https://rdap.org/domain/',
            };

            $response = Http::timeout(12)->acceptJson()->get($rdapBase . rawurlencode($domain));
            if (!$response->successful()) {
                $result['error'] = 'RDAP lookup failed with HTTP ' . $response->status();
                return $result;
            }

            $data = $response->json();
            $result['source'] = $data['rdapConformance'][0] ?? 'RDAP';
            $result['status'] = 'ok';
            $result['registrar'] = data_get($data, 'entities.0.vcardArray.1.1.3.0');
            $result['nameservers'] = collect($data['nameservers'] ?? [])->pluck('ldhName')->filter()->values()->all();
            $result['statuses'] = $data['status'] ?? [];

            foreach (($data['events'] ?? []) as $event) {
                if (($event['eventAction'] ?? null) === 'registration') {
                    $result['created_at'] = $event['eventDate'] ?? null;
                }
                if (($event['eventAction'] ?? null) === 'expiration') {
                    $result['expires_at'] = $event['eventDate'] ?? null;
                }
            }

            if ($result['expires_at']) {
                $result['days_remaining'] = now()->diffInDays(\Carbon\Carbon::parse($result['expires_at']), false);
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }
}
