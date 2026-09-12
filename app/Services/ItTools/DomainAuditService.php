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

        if ($tld === 'vn') {
            // VNNIC does not expose a public HTTPS RDAP endpoint. Try the
            // registry WHOIS socket first, then a public VN WHOIS API fallback,
            // followed by generic read-only WHOIS aggregators.
            $whois = $this->whoisVn($domain);
            if ($whois !== null) return $whois;

            foreach ([
                fn () => $this->whoisNetVn($domain),
                fn () => $this->whoisLs($domain),
                fn () => $this->whoisHtmlFallback($domain),
            ] as $fallback) {
                try {
                    $data = $fallback();
                    if ($data !== null) return $data;
                } catch (\Throwable) {
                    // Continue to the next read-only fallback.
                }
            }

            $result['source'] = 'VNNIC WHOIS';
            $result['error'] = 'VNNIC registry lookup unavailable from this server.';
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

        return $this->parseWhoisText($domain, $raw, 'VNNIC WHOIS');
    }

    private function whoisNetVn(string $domain): ?array
    {
        $response = Http::timeout(10)->get('https://www.whois.net.vn/whois.php', [
            'domain' => $domain,
            'act' => 'getwhois',
        ]);

        if (!$response->successful()) {
            // The provider documents the same endpoint over HTTP as well.
            $response = Http::timeout(10)->get('http://www.whois.net.vn/whois.php', [
                'domain' => $domain,
                'act' => 'getwhois',
            ]);
        }

        if (!$response->successful()) return null;

        $raw = trim($response->body());
        if ($raw === '' || strcasecmp($raw, 'Domain Name not found') === 0) return null;

        return $this->parseWhoisText($domain, $raw, 'WHOIS.NET.VN');
    }

    private function whoisLs(string $domain): ?array
    {
        $response = Http::timeout(10)->acceptJson()->get('https://whois.ls/json/' . rawurlencode($domain));
        if (!$response->successful()) return null;

        $data = $response->json();
        if (!is_array($data)) return null;
        $data = $data['data'] ?? $data;
        if (is_string($data)) return $this->parseWhoisText($domain, $data, 'WHOIS.LS');
        if (!is_array($data)) return null;

        $result = $this->emptyResult($domain, 'WHOIS.LS');
        $result['registrar'] = $this->firstValue($data, ['registrar', 'sponsoring_registrar', 'registrar_name']);
        $result['created_at'] = $this->firstValue($data, ['creation_date', 'created', 'registered_on']);
        $result['expires_at'] = $this->firstValue($data, ['expiration_date', 'registry_expiry_date', 'expires_on', 'expiry_date']);
        $result['statuses'] = $this->toList($data['status'] ?? ($data['statuses'] ?? []));
        $result['nameservers'] = array_values(array_unique(array_filter(array_map('strtolower', $this->toList($data['name_servers'] ?? ($data['nameservers'] ?? []))))));

        return $this->finalizeResult($result);
    }

    private function whoisHtmlFallback(string $domain): ?array
    {
        $response = Http::timeout(10)->get('https://nicenic.com/whois/', ['query' => $domain]);
        if (!$response->successful()) return null;
        $html = $response->body();
        if ($html === '') return null;

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $result = $this->emptyResult($domain, 'NiceNIC WHOIS');
        $result['registrar'] = $this->textField($text, ['Registrar', 'Sponsoring Registrar', 'Registrar Name']);
        $result['created_at'] = $this->textField($text, ['Registration Time', 'Creation Date', 'Registered On', 'Creation Time']);
        $result['expires_at'] = $this->textField($text, ['Expiration Time', 'Expiration Date', 'Expires On', 'Registry Expiry Date']);
        $result['statuses'] = $this->textFields($text, ['Domain Status', 'Status']);
        $result['nameservers'] = $this->textFields($text, ['Name Server', 'Nameserver', 'NameServer']);

        return $this->finalizeResult($result);
    }

    private function parseWhoisText(string $domain, string $raw, string $source): ?array
    {
        if (trim($raw) === '') return null;

        $result = $this->emptyResult($domain, $source);
        $result['registrar'] = $this->whoisField($raw, ['Registrar', 'Sponsoring Registrar', 'Registrant Organization']);
        $result['created_at'] = $this->whoisField($raw, ['Creation Date', 'Created Date', 'Registered Date', 'Registration Time', 'Creation Time']);
        $result['expires_at'] = $this->whoisField($raw, ['Expiration Date', 'Expiry Date', 'Registry Expiry Date', 'Expiration Time', 'Expiry Time']);
        $result['statuses'] = $this->whoisFields($raw, ['Domain Status', 'Status']);
        $result['nameservers'] = $this->whoisNameservers($raw);

        return $this->finalizeResult($result);
    }

    private function emptyResult(string $domain, string $source): array
    {
        return [
            'domain' => $domain,
            'status' => 'unavailable',
            'registrar' => null,
            'created_at' => null,
            'expires_at' => null,
            'days_remaining' => null,
            'statuses' => [],
            'nameservers' => [],
            'source' => $source,
            'error' => null,
        ];
    }

    private function finalizeResult(array $result): ?array
    {
        if ($result['expires_at']) {
            try { $result['days_remaining'] = now()->diffInDays(Carbon::parse($result['expires_at']), false); }
            catch (\Throwable) { /* Keep null if the provider uses an unknown date format. */ }
        }

        if ($result['registrar'] || $result['created_at'] || $result['expires_at'] || $result['nameservers']) {
            $result['status'] = 'ok';
            return $result;
        }

        return null;
    }

    private function firstValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if (is_array($value)) $value = $value[0] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') return trim((string) $value);
        }
        return null;
    }

    private function toList(mixed $value): array
    {
        if (is_array($value)) return array_values(array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : null, $value)));
        if (is_scalar($value) && trim((string) $value) !== '') return [trim((string) $value)];
        return [];
    }

    private function textField(string $text, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (preg_match('/^\s*' . preg_quote($label, '/') . '\s*:\s*(.+)$/im', $text, $m)) return trim($m[1]);
        }
        return null;
    }

    private function textFields(string $text, array $labels): array
    {
        $values = [];
        foreach ($labels as $label) {
            if (preg_match_all('/^\s*' . preg_quote($label, '/') . '\s*:\s*(.+)$/im', $text, $m)) {
                $values = array_merge($values, array_map('trim', $m[1]));
            }
        }
        return array_values(array_unique(array_filter($values)));
    }

    private function whoisField(string $raw, array $labels): ?string
    {
        return $this->textField($raw, $labels);
    }

    private function whoisFields(string $raw, array $labels): array
    {
        return $this->textFields($raw, $labels);
    }

    private function whoisNameservers(string $raw): array
    {
        $values = $this->textFields($raw, ['Name Server', 'Nameserver', 'NameServer']);
        return array_values(array_unique(array_map('strtolower', $values)));
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
