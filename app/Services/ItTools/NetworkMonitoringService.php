<?php

namespace App\Services\ItTools;

use App\Models\Service;
use App\Models\ServiceMonitorEvent;
use Carbon\Carbon;
use Symfony\Component\Process\Process;

class NetworkMonitoringService
{
    public function monitor(Service $service): array
    {
        if ($service->status !== 'active' || !in_array($service->monitor_check_method, ['ping', 'port'], true) || !$service->monitor_target) {
            return ['checked' => false, 'reason' => 'Monitoring is not configured.'];
        }

        if (!$this->isDue($service)) {
            return ['checked' => false, 'reason' => 'Not due yet.'];
        }

        return $this->executeCheck($service, true);
    }

    /**
     * REAL connection test only. Does not persist monitoring state, incidents, or alerts.
     */
    public function test(Service $service, array $config = []): array
    {
        $target = (string) ($config['monitor_target'] ?? $service->monitor_target ?? '');
        $method = (string) ($config['monitor_check_method'] ?? $service->monitor_check_method ?? '');
        $timeout = (int) ($config['monitor_timeout_seconds'] ?? $service->monitor_timeout_seconds ?? 5);
        $timeout = max(1, min($timeout, 60));

        if (!in_array($method, ['ping', 'port'], true) || trim($target) === '') {
            return ['checked' => false, 'online' => false, 'reason' => 'Monitoring is not configured.'];
        }

        $target = $this->normalizeHostOrIp($target);

        if (!$target || !$this->validTarget($target)) {
            return ['checked' => false, 'online' => false, 'reason' => 'Invalid monitor target.'];
        }

        if ($method === 'port') {
            $ports = $this->normalizePorts($config['monitor_ports'] ?? $service->monitor_ports, $config['monitor_port'] ?? $service->monitor_port);
            if (!$ports) {
                return ['checked' => false, 'online' => false, 'reason' => 'No valid TCP port configured.'];
            }

            return ['checked' => true, 'service_id' => $service->id] + $this->checkPorts($target, $ports, $timeout);
        }

        return ['checked' => true, 'service_id' => $service->id] + $this->ping($target, $timeout);
    }

    public function detectSsl(Service $service, ?string $target = null): array
    {
        $service->loadMissing('serviceType');

        if (strtoupper((string) $service->serviceType?->code) !== 'WEBSITE') {
            return ['ssl_detected' => false, 'error' => 'SSL detection is available only for Website services.'];
        }

        $host = $this->normalizeHost((string) ($target ?? $service->monitor_target ?: $service->value));

        if (!$host || filter_var($host, FILTER_VALIDATE_IP)) {
            $this->saveSslState($service, false, null, null, null, 'error');
            return ['ssl_detected' => false, 'error' => 'A valid website hostname is required for SSL detection.'];
        }

        $timeout = max(2, min((int) ($service->monitor_timeout_seconds ?: 5), 30));

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'capture_peer_cert_chain' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
            ],
        ]);

        $errno = 0;
        $error = '';
        $socket = @stream_socket_client(
            'ssl://' . $host . ':443',
            $errno,
            $error,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            $this->saveSslState($service, false, null, null, null, 'error');
            return [
                'ssl_detected' => false,
                'error' => $this->sslConnectionError($error, $errno, $host),
            ];
        }

        $params = stream_context_get_params($socket);
        fclose($socket);

        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$certificate) {
            $this->saveSslState($service, false, null, null, null, 'error');
            return ['ssl_detected' => false, 'error' => 'HTTPS connection succeeded but no peer certificate was returned.'];
        }

        $info = @openssl_x509_parse($certificate);
        if (!$info || empty($info['validTo_time_t'])) {
            $this->saveSslState($service, false, null, null, null, 'error');
            return ['ssl_detected' => false, 'error' => 'The peer certificate could not be parsed.'];
        }

        $validFrom = !empty($info['validFrom_time_t'])
            ? Carbon::createFromTimestamp((int) $info['validFrom_time_t'])
            : null;

        $expiry = Carbon::createFromTimestamp((int) $info['validTo_time_t']);
        $subjectCn = (string) ($info['subject']['CN'] ?? '');
        $issuer = (string) (
            $info['issuer']['CN']
            ?? $info['issuer']['O']
            ?? ''
        );

        $status = $expiry->isPast() ? 'expired' : 'valid';
        $daysRemaining = now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false);

        $this->saveSslState($service, true, $validFrom, $expiry, $issuer ?: null, $status);

        return [
            'ssl_detected' => true,
            'ssl_status' => $status,
            'issuer' => $issuer ?: null,
            'subject_cn' => $subjectCn ?: null,
            'valid_from' => $validFrom?->toDateString(),
            'expires_at' => $expiry->toIso8601String(),
            'saved_ssl_expiry_date' => $expiry->toDateString(),
            'days_remaining' => $daysRemaining,
        ];
    }

    private function sslConnectionError(string $error, int $errno, string $host): string
    {
        $message = trim($error);

        if ($message === '') {
            $message = 'Unable to establish a trusted HTTPS/TLS connection to ' . $host . ':443.';
        }

        if ($errno !== 0) {
            $message .= ' (errno ' . $errno . ')';
        }

        return mb_substr($message, 0, 1000);
    }

    private function saveSslState(Service $service, bool $detected, ?Carbon $validFrom, ?Carbon $expiry, ?string $issuer, string $status): void
    {
        $service->ssl_detected = $detected;
        $service->ssl_valid_from_date = $validFrom?->toDateString();
        $service->ssl_expiry_date = $expiry?->toDateString();
        $service->ssl_issuer = $issuer;
        $service->ssl_status = $status;
        $service->ssl_last_checked_at = now();

        if (!$detected || !$expiry) {
            $service->ssl_alert_stage = 0;
            $service->ssl_last_alert_at = null;
        }

        $service->save();
    }

    private function normalizeHost(string $target): ?string
    {
        $target = trim($target);

        if ($target === '') {
            return null;
        }

        $candidate = preg_match('/^https?:\/\//i', $target)
            ? $target
            : 'https://' . $target;

        $host = strtolower(trim((string) parse_url($candidate, PHP_URL_HOST)));

        return $host !== '' ? rtrim($host, '.') : null;
    }

    private function normalizeHostOrIp(string $target): ?string
    {
        $target = trim($target);

        if ($target === '') {
            return null;
        }

        if (filter_var($target, FILTER_VALIDATE_IP)) {
            return $target;
        }

        return $this->normalizeHost($target);
    }

    private function normalizePorts($ports, $fallbackPort = null): array
    {
        if (is_string($ports)) {
            $ports = preg_split('/[\s,;]+/', $ports, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!is_array($ports)) {
            $ports = [];
        }

        if (!$ports && $fallbackPort) {
            $ports = [(int) $fallbackPort];
        }

        $ports = array_map('intval', $ports);
        $ports = array_values(array_unique(array_filter(
            $ports,
            fn (int $port) => $port >= 1 && $port <= 65535
        )));

        return array_slice($ports, 0, 20);
    }

    private function executeCheck(Service $service, bool $persist): array
    {
        $service->loadMissing('serviceType');
        $timeout = (int) ($service->monitor_timeout_seconds ?: 5);

        if ($service->monitor_check_method === 'port') {
            $ports = $this->portsFor($service);

            $result = $ports
                ? $this->checkPorts($this->normalizeHostOrIp((string) $service->monitor_target), $ports, $timeout)
                : [
                    'online' => false,
                    'latency_ms' => null,
                    'packet_loss_percent' => 100.0,
                    'error' => 'No monitor port configured.',
                    'port_results' => [],
                ];
        } else {
            $target = $this->normalizeHostOrIp((string) $service->monitor_target);
            $result = $target
                ? $this->ping($target, $timeout)
                : [
                    'online' => false,
                    'latency_ms' => null,
                    'packet_loss_percent' => 100.0,
                    'error' => 'Invalid monitor target.',
                ];
        }

        if ($persist) {
            $this->persist($service, $result);
        }

        return ['checked' => true, 'service_id' => $service->id] + $result;
    }

    private function isDue(Service $service): bool
    {
        if (!$service->monitor_last_checked_at) {
            return true;
        }

        $interval = max(30, (int) ($service->monitor_interval_seconds ?: 60));

        return now()->greaterThanOrEqualTo(
            $service->monitor_last_checked_at->copy()->addSeconds($interval)
        );
    }

    private function ping(string $target, int $timeout): array
    {
        if (!$this->validTarget($target)) {
            return [
                'online' => false,
                'latency_ms' => null,
                'packet_loss_percent' => 100.0,
                'error' => 'Invalid monitor target.',
            ];
        }

        $started = microtime(true);
        $process = new Process([
            'ping',
            '-c',
            '1',
            '-W',
            (string) max(1, min($timeout, 60)),
            $target,
        ]);

        $process->setTimeout(max(2, min($timeout + 2, 65)));
        $process->run();

        $output = $process->getOutput() . "\n" . $process->getErrorOutput();
        $latency = null;

        if (preg_match('/time[=<]([\d.]+)\s*ms/i', $output, $m)) {
            $latency = (float) $m[1];
        }

        return [
            'online' => $process->isSuccessful(),
            'latency_ms' => $latency,
            'packet_loss_percent' => $process->isSuccessful() ? 0.0 : 100.0,
            'error' => $process->isSuccessful()
                ? null
                : trim(mb_substr($process->getErrorOutput() ?: 'PING failed.', 0, 1000)),
            'elapsed_ms' => round((microtime(true) - $started) * 1000, 1),
        ];
    }

    private function checkPorts(string $target, array $ports, int $timeout): array
    {
        if (!$this->validTarget($target)) {
            return [
                'online' => false,
                'latency_ms' => null,
                'packet_loss_percent' => 100.0,
                'error' => 'Invalid monitor target.',
                'port_results' => [],
            ];
        }

        $started = microtime(true);
        $results = [];

        foreach ($ports as $port) {
            $results[] = $this->checkPort($target, (int) $port, $timeout) + [
                'port' => (int) $port,
            ];
        }

        $failed = array_values(array_filter(
            $results,
            fn (array $r) => !$r['online']
        ));

        $latencies = array_values(array_filter(
            array_map(fn (array $r) => $r['latency_ms'], $results),
            fn ($v) => $v !== null
        ));

        $allOnline = !$failed;
        $failedText = implode(', ', array_map(
            fn (array $r) => (string) $r['port'],
            $failed
        ));

        return [
            'online' => $allOnline,
            'latency_ms' => $latencies ? max($latencies) : null,
            'packet_loss_percent' => $allOnline
                ? 0.0
                : round((count($failed) / max(1, count($results))) * 100, 2),
            'error' => $allOnline ? null : 'TCP port(s) offline: ' . $failedText,
            'elapsed_ms' => round((microtime(true) - $started) * 1000, 1),
            'port_results' => $results,
        ];
    }

    private function checkPort(string $target, int $port, int $timeout): array
    {
        if ($port < 1 || $port > 65535) {
            return [
                'online' => false,
                'latency_ms' => null,
                'packet_loss_percent' => 100.0,
                'error' => 'Invalid monitor port.',
            ];
        }

        $started = microtime(true);
        $errno = 0;
        $error = '';

        $socketHost = filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? '[' . $target . ']'
            : $target;

        $stream = @stream_socket_client(
            'tcp://' . $socketHost . ':' . $port,
            $errno,
            $error,
            max(1, min($timeout, 60)),
            STREAM_CLIENT_CONNECT
        );

        $latency = round((microtime(true) - $started) * 1000, 1);

        if (is_resource($stream)) {
            fclose($stream);

            return [
                'online' => true,
                'latency_ms' => $latency,
                'packet_loss_percent' => 0.0,
                'error' => null,
            ];
        }

        return [
            'online' => false,
            'latency_ms' => $latency,
            'packet_loss_percent' => 100.0,
            'error' => mb_substr($error ?: 'TCP connection failed.', 0, 1000),
        ];
    }

    private function portsFor(Service $service): array
    {
        return $this->normalizePorts($service->monitor_ports, $service->monitor_port);
    }

    private function persist(Service $service, array $result): void
    {
        $isOnline = (bool) $result['online'];
        $now = now();

        $service->monitor_last_checked_at = $now;
        $service->monitor_last_latency_ms = $result['latency_ms'];
        $service->monitor_packet_loss_percent = $result['packet_loss_percent'];

        if ($isOnline) {
            $service->monitor_status = 'online';
            $service->monitor_failure_count = 0;
            $service->monitor_down_since = null;

            $open = ServiceMonitorEvent::query()
                ->where('service_id', $service->id)
                ->where('status', 'open')
                ->latest('id')
                ->first();

            if ($open) {
                $open->update([
                    'status' => 'resolved',
                    'event_type' => 'recovered',
                    'resolved_at' => $now,
                    'duration_seconds' => max(0, $open->started_at->diffInSeconds($now)),
                    'latency_ms' => $result['latency_ms'],
                    'packet_loss_percent' => $result['packet_loss_percent'],
                    'error' => null,
                ]);
            }
        } else {
            $service->monitor_status = 'offline';
            $service->monitor_failure_count = ((int) $service->monitor_failure_count) + 1;

            if (!$service->monitor_down_since) {
                $service->monitor_down_since = $now;
            }

            $open = ServiceMonitorEvent::query()
                ->where('service_id', $service->id)
                ->where('status', 'open')
                ->exists();

            if (!$open) {
                $failedPort = collect($result['port_results'] ?? [])->firstWhere('online', false);

                ServiceMonitorEvent::create([
                    'service_id' => $service->id,
                    'event_type' => 'down',
                    'check_method' => $service->monitor_check_method,
                    'target' => $service->monitor_target,
                    'port' => $failedPort['port'] ?? $service->monitor_port,
                    'status' => 'open',
                    'latency_ms' => $result['latency_ms'],
                    'packet_loss_percent' => $result['packet_loss_percent'],
                    'started_at' => $now,
                    'error' => $result['error'],
                ]);
            }
        }

        $service->save();
        app(NetworkAlertEmailService::class)->process();
    }

    private function validTarget(string $target): bool
    {
        $target = trim($target);

        if (filter_var($target, FILTER_VALIDATE_IP)) {
            return true;
        }

        return (bool) preg_match(
            '/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/',
            $target
        );
    }
}
