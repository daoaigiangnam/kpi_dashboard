<?php

namespace App\Services\IT;

class DomainAuditService
{
    public function audit(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $result = [
            'domain' => $domain,
            'status' => false,
            'source' => null,
            'registrar' => null,
            'created_at' => null,
            'expires_at' => null,
            'days_remaining' => null,
            'domain_status' => [],
            'nameservers' => [],
            'error' => null,
        ];

        $tld = strrpos($domain, '.');
        if ($tld === false || !preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain)) {
            $result['error'] = 'Invalid domain name';
            return $result;
        }

        $bootstrapUrl = 'https://rdap.org/domain/' . rawurlencode($domain);
        $json = $this->getJson($bootstrapUrl);
        if ($json === null) {
            $result['error'] = 'RDAP lookup failed';
            return $result;
        }

        $result['status'] = true;
        $result['source'] = 'RDAP';
        $result['registrar'] = $this->registrar($json);
        $result['nameservers'] = array_values(array_filter(array_map(
            static fn (array $ns): ?string => isset($ns['ldhName']) ? strtolower($ns['ldhName']) : null,
            $json['nameservers'] ?? []
        )));
        $result['domain_status'] = $json['status'] ?? [];

        foreach ($json['events'] ?? [] as $event) {
            $date = $event['eventDate'] ?? null;
            if (!$date) {
                continue;
            }
            if (($event['eventAction'] ?? '') === 'registration') {
                $result['created_at'] = $date;
            }
            if (in_array(($event['eventAction'] ?? ''), ['expiration', 'expiry'], true)) {
                $result['expires_at'] = $date;
            }
        }

        if ($result['expires_at']) {
            $expires = strtotime($result['expires_at']);
            if ($expires !== false) {
                $result['days_remaining'] = (int) floor(($expires - time()) / 86400);
            }
        }

        return $result;
    }

    private function getJson(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'KPI Dashboard IT Outsourcing Tools/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/rdap+json, application/json'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if ($body === false || trim($body) === '') {
            return null;
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    private function registrar(array $data): ?string
    {
        foreach ($data['entities'] ?? [] as $entity) {
            foreach ($entity['roles'] ?? [] as $role) {
                if ($role !== 'registrar') {
                    continue;
                }
                foreach ($entity['vcardArray'][1] ?? [] as $entry) {
                    if (($entry[0] ?? null) === 'fn' && isset($entry[3])) {
                        return (string) $entry[3];
                    }
                }
                return $entity['handle'] ?? null;
            }
        }
        return null;
    }
}
