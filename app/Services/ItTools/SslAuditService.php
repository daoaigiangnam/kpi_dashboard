<?php

namespace App\Services\ItTools;

use Illuminate\Support\Facades\Http;

class SslAuditService
{
    public function check(string $host, int $port = 443): array
    {
        $host = strtolower(trim($host));
        $result = [
            'host' => $host, 'port' => $port, 'status' => 'error', 'subject' => null,
            'san' => [], 'issuer' => null, 'vendor' => null, 'valid_from' => null,
            'valid_to' => null, 'days_remaining' => null, 'tls_version' => null,
            'cipher' => null, 'verify' => false, 'error' => null,
        ];

        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $errno = 0; $errstr = '';
        $stream = @stream_socket_client("tls://{$host}:{$port}", $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $context);
        if (!$stream) { $result['error'] = $errstr ?: "TLS connection failed ({$errno})"; return $result; }

        $params = stream_context_get_params($stream);
        fclose($stream);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) { $result['error'] = 'Certificate not provided by server.'; return $result; }

        $parsed = openssl_x509_parse($cert);
        if (!$parsed) { $result['error'] = 'Unable to parse certificate.'; return $result; }

        $result['status'] = 'ok';
        $result['subject'] = $parsed['subject']['CN'] ?? null;
        $result['issuer'] = $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? null);
        $result['valid_from'] = isset($parsed['validFrom_time_t']) ? date(DATE_ATOM, $parsed['validFrom_time_t']) : null;
        $result['valid_to'] = isset($parsed['validTo_time_t']) ? date(DATE_ATOM, $parsed['validTo_time_t']) : null;
        $result['san'] = isset($parsed['extensions']['subjectAltName']) ? array_map('trim', preg_split('/\s*,\s*/', $parsed['extensions']['subjectAltName'])) : [];
        $result['vendor'] = $this->vendor((string) ($result['issuer'] ?? ''));
        if ($parsed['validTo_time_t'] ?? null) $result['days_remaining'] = (int) floor(($parsed['validTo_time_t'] - time()) / 86400);
        $result['verify'] = $this->hostnameMatches($host, $result['subject'], $result['san']);
        return $result;
    }

    private function hostnameMatches(string $host, ?string $cn, array $san): bool
    {
        $names = array_values(array_filter(array_merge($cn ? [$cn] : [], array_map(function ($v) {
            return str_starts_with($v, 'DNS:') ? substr($v, 4) : null;
        }, $san))));
        foreach ($names as $name) {
            if (strcasecmp($host, $name) === 0) return true;
            if (str_starts_with($name, '*.')) {
                $suffix = substr($name, 1);
                if (str_ends_with($host, $suffix) && substr_count($host, '.') === substr_count($name, '.')) return true;
            }
        }
        return false;
    }

    private function vendor(string $issuer): ?string
    {
        foreach (['Let\'s Encrypt'=>'Let\'s Encrypt','DigiCert'=>'DigiCert','Sectigo'=>'Sectigo','GlobalSign'=>'GlobalSign','Google Trust Services'=>'Google Trust Services','ZeroSSL'=>'ZeroSSL','Entrust'=>'Entrust'] as $needle => $name) {
            if (stripos($issuer, $needle) !== false) return $name;
        }
        return $issuer !== '' ? $issuer : null;
    }
}
