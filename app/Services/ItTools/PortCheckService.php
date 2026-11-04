<?php

namespace App\Services\ItTools;

class PortCheckService
{
    /**
     * Controlled TCP connectivity check for explicitly supplied ports.
     * This tool intentionally does not scan port ranges or discover arbitrary ports.
     */
    public function check(string $host, array $ports): array
    {
        $host = trim($host);
        $resolved = $this->resolveHost($host);
        $target = $resolved['address'] ?? $host;
        $results = [];

        foreach ($ports as $port) {
            $port = (int) $port;
            if ($port < 1 || $port > 65535) {
                continue;
            }
            $results[] = $this->checkPort($target, $port);
        }

        return [
            'host' => $host,
            'target' => $target,
            'resolved_ip' => $resolved['address'] ?? null,
            'family' => $resolved['family'] ?? null,
            'ptr' => $this->reverseDns($resolved['address'] ?? null),
            'ports' => $results,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function checkPort(string $host, int $port): array
    {
        $started = microtime(true);
        $errno = 0;
        $error = '';
        $socketHost = $this->isIpv6($host) ? '[' . $host . ']' : $host;
        $stream = @stream_socket_client(
            'tcp://' . $socketHost . ':' . $port,
            $errno,
            $error,
            2.5,
            STREAM_CLIENT_CONNECT
        );
        $ms = round((microtime(true) - $started) * 1000, 1);

        if (is_resource($stream)) {
            stream_set_timeout($stream, 1);
            fclose($stream);
            return [
                'port' => $port,
                'status' => 'open',
                'service' => $this->serviceName($port),
                'response_time_ms' => $ms,
                'error' => null,
            ];
        }

        return [
            'port' => $port,
            'status' => $this->failureStatus($errno, $error),
            'service' => $this->serviceName($port),
            'response_time_ms' => $ms,
            'error' => $error ?: null,
        ];
    }

    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['address' => $host, 'family' => 'IPv4'];
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return ['address' => $host, 'family' => 'IPv6'];
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $record) {
            if (!empty($record['ip'])) {
                return ['address' => $record['ip'], 'family' => 'IPv4'];
            }
            if (!empty($record['ipv6'])) {
                return ['address' => $record['ipv6'], 'family' => 'IPv6'];
            }
        }

        $ip = gethostbyname($host);
        return $ip !== $host ? ['address' => $ip, 'family' => 'IPv4'] : [];
    }

    private function reverseDns(?string $ip): ?string
    {
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }
        $ptr = @gethostbyaddr($ip);
        return $ptr && $ptr !== $ip ? $ptr : null;
    }

    private function serviceName(int $port): string
    {
        return match ($port) {
            20 => 'FTP-Data', 21 => 'FTP', 22 => 'SSH', 23 => 'Telnet',
            25 => 'SMTP', 53 => 'DNS', 80 => 'HTTP', 110 => 'POP3',
            111 => 'RPCBind', 135 => 'MSRPC', 139 => 'NetBIOS', 143 => 'IMAP',
            161 => 'SNMP', 389 => 'LDAP', 443 => 'HTTPS', 445 => 'SMB',
            465 => 'SMTPS', 587 => 'SMTP Submission', 636 => 'LDAPS',
            993 => 'IMAPS', 995 => 'POP3S', 1433 => 'MSSQL', 1521 => 'Oracle',
            2049 => 'NFS', 2375 => 'Docker API', 2376 => 'Docker TLS',
            3000 => 'App/Node', 3306 => 'MySQL', 3389 => 'RDP',
            5432 => 'PostgreSQL', 5672 => 'AMQP', 6379 => 'Redis',
            6443 => 'Kubernetes API', 8080 => 'HTTP-Alt', 8443 => 'HTTPS-Alt',
            9200 => 'Elasticsearch', 27017 => 'MongoDB',
            default => 'TCP',
        };
    }

    private function failureStatus(int $errno, string $error): string
    {
        $message = strtolower($error);
        if (in_array($errno, [111, 61, 10061], true) || str_contains($message, 'refused')) {
            return 'closed';
        }
        if (in_array($errno, [110, 60, 10060], true) || str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return 'filtered/timeout';
        }
        return 'unreachable/error';
    }

    private function isIpv6(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }
}
