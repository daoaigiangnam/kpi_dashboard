<?php

namespace App\Services\ItTools;

use App\Models\Service;
use App\Models\ServiceMonitorEvent;
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

        $result = $service->monitor_check_method === 'port'
            ? $this->checkPort($service->monitor_target, (int) $service->monitor_port, (int) ($service->monitor_timeout_seconds ?: 5))
            : $this->ping($service->monitor_target, (int) ($service->monitor_timeout_seconds ?: 5));

        $this->persist($service, $result);

        return ['checked' => true, 'service_id' => $service->id] + $result;
    }

    private function isDue(Service $service): bool
    {
        if (!$service->monitor_last_checked_at) return true;
        $interval = max(30, (int) ($service->monitor_interval_seconds ?: 60));
        return now()->greaterThanOrEqualTo($service->monitor_last_checked_at->copy()->addSeconds($interval));
    }

    private function ping(string $target, int $timeout): array
    {
        if (!$this->validTarget($target)) {
            return ['online' => false, 'latency_ms' => null, 'packet_loss_percent' => 100.0, 'error' => 'Invalid monitor target.'];
        }

        $started = microtime(true);
        $process = new Process(['ping', '-c', '1', '-W', (string) max(1, min($timeout, 60)), $target]);
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
            'error' => $process->isSuccessful() ? null : trim(mb_substr($process->getErrorOutput() ?: 'PING failed.', 0, 1000)),
            'elapsed_ms' => round((microtime(true) - $started) * 1000, 1),
        ];
    }

    private function checkPort(string $target, int $port, int $timeout): array
    {
        if (!$this->validTarget($target)) {
            return ['online' => false, 'latency_ms' => null, 'packet_loss_percent' => 100.0, 'error' => 'Invalid monitor target.'];
        }
        if ($port < 1 || $port > 65535) {
            return ['online' => false, 'latency_ms' => null, 'packet_loss_percent' => 100.0, 'error' => 'Invalid monitor port.'];
        }

        $started = microtime(true);
        $errno = 0;
        $error = '';
        $socketHost = filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '['.$target.']' : $target;
        $stream = @stream_socket_client('tcp://'.$socketHost.':'.$port, $errno, $error, max(1, min($timeout, 60)), STREAM_CLIENT_CONNECT);
        $latency = round((microtime(true) - $started) * 1000, 1);

        if (is_resource($stream)) {
            fclose($stream);
            return ['online' => true, 'latency_ms' => $latency, 'packet_loss_percent' => 0.0, 'error' => null, 'elapsed_ms' => $latency];
        }

        return ['online' => false, 'latency_ms' => $latency, 'packet_loss_percent' => 100.0, 'error' => mb_substr($error ?: 'TCP connection failed.', 0, 1000), 'elapsed_ms' => $latency];
    }

    private function persist(Service $service, array $result): void
    {
        $wasOnline = $service->monitor_status === 'online';
        $isOnline = (bool) $result['online'];
        $now = now();

        $service->monitor_last_checked_at = $now;
        $service->monitor_last_latency_ms = $result['latency_ms'];
        $service->monitor_packet_loss_percent = $result['packet_loss_percent'];

        if ($isOnline) {
            $service->monitor_status = 'online';
            $service->monitor_failure_count = 0;
            $service->monitor_down_since = null;

            $open = ServiceMonitorEvent::query()->where('service_id', $service->id)->where('status', 'open')->latest('id')->first();
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
            if (!$service->monitor_down_since) $service->monitor_down_since = $now;

            $open = ServiceMonitorEvent::query()->where('service_id', $service->id)->where('status', 'open')->exists();
            if (!$open) {
                ServiceMonitorEvent::create([
                    'service_id' => $service->id,
                    'event_type' => 'down',
                    'check_method' => $service->monitor_check_method,
                    'target' => $service->monitor_target,
                    'port' => $service->monitor_port,
                    'status' => 'open',
                    'latency_ms' => $result['latency_ms'],
                    'packet_loss_percent' => $result['packet_loss_percent'],
                    'started_at' => $now,
                    'error' => $result['error'],
                ]);
            }
        }

        $service->save();
    }

    private function validTarget(string $target): bool
    {
        $target = trim($target);
        if (filter_var($target, FILTER_VALIDATE_IP)) return true;
        return (bool) preg_match('/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/', $target);
    }
}
