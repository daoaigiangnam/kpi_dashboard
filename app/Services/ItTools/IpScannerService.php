<?php

namespace App\Services\ItTools;

class IpScannerService
{
    private const MAX_HOSTS = 256;
    private const MAX_CUSTOM_PORTS = 50;
    private const PROBE_PORTS = [80, 443, 22];
    private const CONNECT_TIMEOUT = 0.25;
    private const BATCH_SIZE = 128;

    public function scan(string $range, array $ports = [], bool $allPorts = false): array
    {
        $ips = $this->expandRange(trim($range));
        if (count($ips) > self::MAX_HOSTS) {
            throw new \InvalidArgumentException('IP range is limited to 256 addresses per scan.');
        }

        if ($allPorts && count($ips) > 1) {
            throw new \InvalidArgumentException('All Ports can only be used when scanning a single IP.');
        }

        $ports = $allPorts ? range(1, 65535) : $this->normalizePorts($ports);
        $results = [];
        foreach ($ips as $ip) {
            $results[] = $this->scanIp($ip, $ports, $allPorts);
        }

        return [
            'range' => $range,
            'total' => count($results),
            'online' => count(array_filter($results, fn ($r) => $r['status'] === 'online')),
            'offline' => count(array_filter($results, fn ($r) => $r['status'] !== 'online')),
            'results' => $results,
            'scanned_at' => now()->toIso8601String(),
        ];
    }

    private function expandRange(string $range): array
    {
        if (str_contains($range, '/')) {
            [$network, $prefix] = array_pad(explode('/', $range, 2), 2, null);
            if (!filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !is_numeric($prefix)) {
                throw new \InvalidArgumentException('Invalid IPv4 CIDR range.');
            }
            $prefix = (int) $prefix;
            if ($prefix < 24 || $prefix > 32) {
                throw new \InvalidArgumentException('IPv4 CIDR must be between /24 and /32.');
            }
            $base = ip2long($network);
            $mask = -1 << (32 - $prefix);
            $networkLong = $base & $mask;
            $count = 1 << (32 - $prefix);
            $ips = [];
            for ($i = 0; $i < $count; $i++) {
                $ips[] = long2ip($networkLong + $i);
            }
            return $ips;
        }

        if (preg_match('/^([^\s]+)\s*-\s*([^\s]+)$/', $range, $m)) {
            if (!filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !filter_var($m[2], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new \InvalidArgumentException('IP range must use IPv4 addresses.');
            }
            $start = ip2long($m[1]);
            $end = ip2long($m[2]);
            if ($start > $end || ($end - $start + 1) > self::MAX_HOSTS) {
                throw new \InvalidArgumentException('IP range must contain 1 to 256 addresses.');
            }
            $ips = [];
            for ($i = $start; $i <= $end; $i++) {
                $ips[] = long2ip($i);
            }
            return $ips;
        }

        if (filter_var($range, FILTER_VALIDATE_IP)) {
            return [$range];
        }

        throw new \InvalidArgumentException('Enter an IPv4 address, CIDR (for example 192.168.1.0/24), or start-end range.');
    }

    private function normalizePorts(array $ports): array
    {
        $ports = collect($ports)
            ->map(fn ($p) => (int) $p)
            ->filter(fn ($p) => $p >= 1 && $p <= 65535)
            ->unique()
            ->values()
            ->all();

        if (!$ports || count($ports) > self::MAX_CUSTOM_PORTS) {
            throw new \InvalidArgumentException('Select 1 to 50 TCP ports.');
        }

        return $ports;
    }

    private function scanIp(string $ip, array $ports, bool $allPorts): array
    {
        $started = microtime(true);
        $probe = $allPorts ? ['online' => false, 'latency_ms' => null] : $this->probe($ip);
        $open = $this->scanPorts($ip, $ports);

        $online = $probe['online'] || $open !== [];
        $latency = $probe['latency_ms'];
        if ($latency === null && $online) {
            $latency = round((microtime(true) - $started) * 1000, 1);
        }

        $ptr = $this->reverseDns($ip);

        return [
            'ip' => $ip,
            'status' => $online ? 'online' : 'offline',
            'response_time_ms' => $latency,
            'ptr' => $ptr,
            'hostname' => $ptr,
            'open_ports' => $open,
            'open_port_count' => count($open),
            'checked_ports' => count($ports),
        ];
    }

    private function probe(string $ip): array
    {
        $started = microtime(true);
        $open = $this->scanPorts($ip, self::PROBE_PORTS);

        return [
            'online' => $open !== [],
            'latency_ms' => $open !== [] ? round((microtime(true) - $started) * 1000, 1) : null,
        ];
    }

    /**
     * Probe TCP ports concurrently in small batches. This avoids the old
     * one-port-at-a-time timeout which could make a /24 scan hit the proxy
     * or PHP-FPM request timeout.
     */
    private function scanPorts(string $ip, array $ports): array
    {
        $open = [];
        foreach (array_chunk($ports, self::BATCH_SIZE) as $batch) {
            $sockets = [];
            foreach ($batch as $port) {
                $errno = 0;
                $error = '';
                $address = 'tcp://' . (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $ip . ']' : $ip) . ':' . $port;
                $socket = @stream_socket_client(
                    $address,
                    $errno,
                    $error,
                    self::CONNECT_TIMEOUT,
                    STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_CONNECT
                );

                if (is_resource($socket)) {
                    stream_set_blocking($socket, false);
                    $sockets[(int) $socket] = ['socket' => $socket, 'port' => $port];
                }
            }

            if (!$sockets) {
                continue;
            }

            $deadline = microtime(true) + self::CONNECT_TIMEOUT + 0.15;
            while ($sockets && microtime(true) < $deadline) {
                $write = array_map(fn ($item) => $item['socket'], array_values($sockets));
                $except = $write;
                $read = [];
                $seconds = max(0, $deadline - microtime(true));
                $sec = (int) floor($seconds);
                $usec = (int) (($seconds - $sec) * 1_000_000);

                $changed = @stream_select($read, $write, $except, $sec, $usec);
                if ($changed === false) {
                    break;
                }
                if ($changed === 0) {
                    break;
                }

                foreach (array_merge($write, $except) as $socket) {
                    $id = (int) $socket;
                    if (!isset($sockets[$id])) {
                        continue;
                    }
                    $port = $sockets[$id]['port'];
                    $meta = @stream_get_meta_data($socket);
                    $timedOut = microtime(true) >= $deadline;
                    if (in_array($socket, $write, true) && !$timedOut && !($meta['timed_out'] ?? false)) {
                        $open[] = ['port' => $port, 'service' => $this->serviceName($port)];
                    }
                    fclose($socket);
                    unset($sockets[$id]);
                }
            }

            foreach ($sockets as $item) {
                @fclose($item['socket']);
            }
        }

        usort($open, fn ($a, $b) => $a['port'] <=> $b['port']);
        return $open;
    }

    private function reverseDns(string $ip): ?string
    {
        $ptr = @gethostbyaddr($ip);
        return $ptr && $ptr !== $ip ? $ptr : null;
    }

    private function serviceName(int $port): string
    {
        return match ($port) {
            20 => 'FTP-Data', 21 => 'FTP', 22 => 'SSH', 23 => 'Telnet', 25 => 'SMTP',
            53 => 'DNS', 80 => 'HTTP', 110 => 'POP3', 111 => 'RPCBind', 135 => 'MSRPC',
            139 => 'NetBIOS', 143 => 'IMAP', 161 => 'SNMP', 389 => 'LDAP', 443 => 'HTTPS',
            445 => 'SMB', 465 => 'SMTPS', 587 => 'SMTP Submission', 636 => 'LDAPS',
            993 => 'IMAPS', 995 => 'POP3S', 1433 => 'MSSQL', 1521 => 'Oracle', 2049 => 'NFS',
            2375 => 'Docker API', 2376 => 'Docker TLS', 3000 => 'App/Node', 3306 => 'MySQL',
            3389 => 'RDP', 5432 => 'PostgreSQL', 5672 => 'AMQP', 6379 => 'Redis',
            6443 => 'Kubernetes API', 8080 => 'HTTP-Alt', 8443 => 'HTTPS-Alt', 9200 => 'Elasticsearch',
            27017 => 'MongoDB', default => 'TCP',
        };
    }
}
