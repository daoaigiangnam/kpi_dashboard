<?php

namespace App\Services\ITOutsourcing;

use Illuminate\Support\Facades\Http;
use Throwable;

class DomainInspector
{
    public function inspect(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = trim($domain, "/ \t\n\r\0\x0B");
        $domain = explode('/', $domain)[0];

        if (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain)) {
            return ['ok' => false, 'domain' => $domain, 'error' => 'Invalid domain'];
        }

        $result = ['ok' => true, 'domain' => $domain, 'checked_at' => now()->toIso8601String()];
        try {
            $tld = substr(strrchr($domain, '.'), 1);
            $bootstrap = Http::timeout(8)->acceptJson()->get("https://rdap.org/domain/" . rawurlencode($domain));
            if ($bootstrap->successful()) {
                $data = $bootstrap->json();
                $result['rdap'] = $data;
                $result['registrar'] = $this->registrar($data);
                $result['domain_status'] = $data['status'] ?? [];
                $result['nameservers'] = collect($data['nameservers'] ?? [])->pluck('ldhName')->filter()->values()->all();
                foreach ($data['events'] ?? [] as $event) {
                    $action = $event['eventAction'] ?? null;
                    if ($action === 'registration') $result['registered_at'] = $event['eventDate'] ?? null;
                    if ($action === 'expiration') $result['expires_at'] = $event['eventDate'] ?? null;
                }
                if (!empty($result['expires_at'])) {
                    $result['days_remaining'] = now()->diffInDays($result['expires_at'], false);
                }
            } else {
                $result['rdap_error'] = 'RDAP lookup failed';
            }
        } catch (Throwable $e) {
            $result['rdap_error'] = $e->getMessage();
        }
        $result['dnssec'] = $this->dnssec($domain);
        $result['dns'] = $this->dns($domain);
        return $result;
    }

    private function registrar(array $data): ?string
    {
        foreach ($data['entities'] ?? [] as $entity) {
            foreach ($entity['roles'] ?? [] as $role) {
                if ($role === 'registrar') {
                    foreach ($entity['vcardArray'][1] ?? [] as $v) {
                        if (($v[0] ?? null) === 'fn') return $v[3] ?? null;
                    }
                }
            }
        }
        return null;
    }

    private function dns(string $domain): array
    {
        $map = [];
        foreach ([DNS_A => 'A', DNS_AAAA => 'AAAA', DNS_CNAME => 'CNAME', DNS_NS => 'NS', DNS_MX => 'MX', DNS_TXT => 'TXT', DNS_SOA => 'SOA', DNS_CAA => 'CAA'] as $type => $name) {
            $records = @dns_get_record($domain, $type) ?: [];
            $map[$name] = $records;
        }
        return $map;
    }

    private function dnssec(string $domain): bool
    {
        if (!function_exists('dns_get_record')) return false;
        $records = @dns_get_record($domain, DNS_ANY) ?: [];
        return collect($records)->contains(fn ($r) => isset($r['type']) && in_array($r['type'], ['DS', 'RRSIG'], true));
    }
}
