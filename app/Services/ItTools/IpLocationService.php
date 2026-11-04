<?php

namespace App\Services\ItTools;

use Illuminate\Support\Facades\Http;

class IpLocationService
{
    public function lookup(?string $ip = null): array
    {
        $ip = trim((string) $ip);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new \InvalidArgumentException('Invalid IP address.');
        }

        $url = $ip !== ''
            ? 'https://ipwho.is/' . rawurlencode($ip)
            : 'https://ipwho.is/';

        $response = Http::timeout(10)->connectTimeout(5)->acceptJson()->get($url);
        if (!$response->successful()) {
            throw new \RuntimeException('IP location provider returned HTTP ' . $response->status() . '.');
        }

        $data = $response->json();
        if (!is_array($data) || ($data['success'] ?? false) !== true) {
            throw new \RuntimeException($data['message'] ?? 'Unable to locate this IP address.');
        }

        return [
            'ip' => $data['ip'] ?? $ip,
            'type' => $data['type'] ?? null,
            'continent' => $data['continent'] ?? null,
            'continent_code' => $data['continent_code'] ?? null,
            'country' => $data['country'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'region' => $data['region'] ?? null,
            'city' => $data['city'] ?? null,
            'postal' => $data['postal'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'timezone' => $data['timezone']['id'] ?? null,
            'timezone_utc' => $data['timezone']['utc'] ?? null,
            'isp' => $data['connection']['isp'] ?? null,
            'org' => $data['connection']['org'] ?? null,
            'asn' => $data['connection']['asn'] ?? null,
            'domain' => $data['connection']['domain'] ?? null,
            'reverse_dns' => $data['reverse'] ?? null,
            'currency' => $data['currency']['name'] ?? null,
            'currency_code' => $data['currency']['code'] ?? null,
            'calling_code' => $data['calling_code'] ?? null,
            'flag' => $data['flag']['emoji'] ?? null,
        ];
    }
}
