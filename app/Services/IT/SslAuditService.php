<?php

namespace App\Services\IT;

class SslAuditService
{
    public function audit(string $host, int $port = 443): array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'capture_peer_cert_chain' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $start = microtime(true);
        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );
        $elapsed = (int) round((microtime(true) - $start) * 1000);

        if (!$client) {
            return ['status' => false, 'host' => $host, 'port' => $port, 'response_ms' => $elapsed, 'error' => $errstr ?: 'TLS connection failed'];
        }

        $params = stream_context_get_params($client);
        fclose($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if (!$cert) {
            return ['status' => false, 'host' => $host, 'port' => $port, 'response_ms' => $elapsed, 'error' => 'No peer certificate'];
        }

        $parsed = @openssl_x509_parse($cert);
        $validTo = isset($parsed['validTo_time_t']) ? (int) $parsed['validTo_time_t'] : null;
        $validFrom = isset($parsed['validFrom_time_t']) ? (int) $parsed['validFrom_time_t'] : null;
        $days = $validTo ? (int) floor(($validTo - time()) / 86400) : null;

        return [
            'status' => true,
            'host' => $host,
            'port' => $port,
            'response_ms' => $elapsed,
            'subject' => $parsed['subject'] ?? [],
            'issuer' => $parsed['issuer'] ?? [],
            'san' => $this->san($parsed['extensions']['subjectAltName'] ?? ''),
            'valid_from' => $validFrom ? date(DATE_ATOM, $validFrom) : null,
            'valid_to' => $validTo ? date(DATE_ATOM, $validTo) : null,
            'days_remaining' => $days,
            'expired' => $validTo !== null && $validTo < time(),
            'certificate_serial' => $parsed['serialNumberHex'] ?? null,
            'signature_algorithm' => $parsed['signatureTypeSN'] ?? null,
        ];
    }

    private function san(string $value): array
    {
        if ($value === '') {
            return [];
        }
        return array_values(array_filter(array_map(static fn (string $part): string => trim(substr($part, 4)), explode(',', $value)), static fn (string $part): bool => str_starts_with($part, 'DNS:')));
    }
}
