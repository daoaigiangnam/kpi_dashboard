<?php

namespace App\Services\ItTools;

use Carbon\Carbon;
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
            'status' => 'unavailable',
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
            $result['status'] = 'error';
            $result['error'] = 'Invalid domain name.';
            return $result;
        }

        $tld = strtolower(substr(strrchr($domain, '.'), 1));

        // .VN does not currently publish a standard IANA RDAP service. Try the
        // registry WHOIS service from the application server before reporting the
        // registration data as unavailable.
        if ($tld === 'vn') {
            $whois = $this->whoisVn($domain);
            if ($whois !== null) return $whois;

            $result['source'] = 'VNNIC WHOIS';
            $result['error'] = 'VNNIC WHOIS lookup unavailable from this server.';
            return $result;
        }

        try {
            $rdapBase = match ($tld) {
                'com' => 'https://rdap.verisign.com/com/v1/domain/',
                'net' => 'https://rdap.verisign.com/net/v1/domain/',
                default => 'https://rdap.org/domain/',
            };

            $response = Http::timeout(12)->acceptJson()->get($rdapBase . rawurlencode($domain));
            if (!$response->successful()) {
                $result['source'] = 'RDAP';
                $result['error'] = 'RDAP lookup unavailable (HTTP ' . $response->status() . ').';
                return $result;
            }

            $data = $response->json();
            $result['source'] = $data['rdapConformance'][0] ?? 'RDAP';
            $result['status'] = 'ok';
            $result['registrar'] = $this->entityName($data['entities'] ?? [], ['registrar']);
            $result['nameservers'] = collect($data['nameservers'] ?? [])->pluck('ldhName')->filter()->values()->all();
            $result['statuses'] = $data['status'] ?? [];

            foreach (($data['events'] ?? []) as $event) {
                if (($event['eventAction'] ?? null) === 'registration') $result['created_at'] = $event['eventDate'] ?? null;
                if (($event['eventAction'] ?? null) === 'expiration') $result['expires_at'] = $event['eventDate'] ?? null;
            }

            if ($result['expires_at']) {
                $result['days_remaining'] = now()->diffInDays(Carbon::parse($result['expires_at']), false);
            }
        } catch (\Throwable $e) {
            $result['source'] = 'RDAP';
            $result['error'] = 'RDAP lookup unavailable: ' . $e->getMessage();
        }

        return $result;
    }

    private function whoisVn(string $domain): ?array
    {
        $socket = @fsockopen('whois.vnnic.vn', 43, $errno, $errstr, 8);
        if (!$socket) return null;

        stream_set_timeout($socket, 8);
        fwrite($socket, $domain . "\r\n");
        $raw = '';
        while (!feof($socket)) {
            $chunk = fgets($socket, 4096);
            if ($chunk === false) break;
            $raw .= $chunk;
            if (strlen($raw) > 100000) break;
        }
        fclose($socket);

        if (trim($raw) === '') return null;

        $result = [
            'domain' => $domain,
            'status' => 'ok',
            'registrar' => $this->whoisField($raw, ['Registrar', 'Sponsoring Registrar', 'Registrant Organization']),
            'created_at' => $this->whoisField($raw, ['Creation Date', 'Created Date', 'Registered Date']),
            'expires_at' => $this->whoisField($raw, ['Expiration Date', 'Expiry Date', 'Registry Expiry Date']),
            'days_remaining' => null,
            'statuses' => $this->whoisFields($raw, ['Domain Status', 'Status']),
            'nameservers' => $this->whoisNameservers($raw),
            'source' => 'VNNIC WHOIS',
            'error' => null,
        ];

        if ($result['expires_at']) {
            try { $result['days_remaining'] = now()->diffInDays(Carbon::parse($result['expires_at']), false); }
            catch (\Throwable) { /* keep null when registry date format is unknown */ }
        }

        if (!$result['registrar'] && !$result['expires_at'] && !$result['nameservers']) return null;
        return $result;
    }

    private function whoisField(string $raw, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (preg_match('/^' . preg_quote($label, '/') . '\s*:\s*(.+)$/im', $raw, $m)) return trim($m[1]);
        }
        return null;
    }

    private function whoisFields(string $raw, array $labels): array
    {
        $values = [];
        foreach ($labels as $label) {
            if (preg_match_all('/^' . preg_quote($label, '/') . '\s*:\s*(.+)$/im', $raw, $m)) {
                $values = array_merge($values, array_map('trim', $m[1]));
            }
        }
        return array_values(array_unique(array_filter($values)));
    }

    private function whoisNameservers(string $raw): array
    {
        $values = [];
        if (preg_match_all('/^(?:Name Server|Nameserver|NameServer)\s*:\s*([^\s]+)$/im', $raw, $m)) {
            $values = array_map('strtolower', $m[1]);
        }
        return array_values(array_unique($values));
    }

    private function entityName(array $entities, array $roles): ?string
    {
        foreach ($entities as $entity) {
            if (array_intersect($roles, $entity['roles'] ?? [])) {
                return data_get($entity, 'vcardArray.1.1.3.0')
                    ?? data_get($entity, 'vcardArray.1.1.2.0');
            }
        }
        return null;
    }
}
