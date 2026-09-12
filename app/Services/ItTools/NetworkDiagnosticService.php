<?php

namespace App\Services\ItTools;

use Symfony\Component\Process\Process;

class NetworkDiagnosticService
{
    public function ping(string $host, int $count = 4): array
    {
        $host = trim($host);
        $count = max(1, min($count, 10));
        $started = microtime(true);

        if (!$this->validTarget($host)) {
            return ['ok' => false, 'error' => 'Invalid host or IP address.'];
        }

        $process = new Process(['ping', '-c', (string) $count, '-W', '2', $host]);
        $process->setTimeout(30);
        $process->run();
        $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());

        return [
            'ok' => $process->isSuccessful(),
            'host' => $host,
            'count' => $count,
            'response_time_ms' => round((microtime(true) - $started) * 1000, 1),
            'output' => $this->cleanOutput($output),
            'summary' => $this->parsePingSummary($output),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function traceroute(string $host, int $maxHops = 30): array
    {
        $host = trim($host);
        $maxHops = max(1, min($maxHops, 64));

        if (!$this->validTarget($host)) {
            return ['ok' => false, 'error' => 'Invalid host or IP address.'];
        }

        $command = $this->commandExists('traceroute')
            ? ['traceroute', '-n', '-m', (string) $maxHops, '-w', '2', $host]
            : ($this->commandExists('tracepath') ? ['tracepath', '-n', '-m', (string) $maxHops, $host] : null);

        if (!$command) {
            return ['ok' => false, 'error' => 'Neither traceroute nor tracepath is installed on the server.'];
        }

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();
        $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());

        return [
            'ok' => $process->isSuccessful() || $output !== '',
            'host' => $host,
            'max_hops' => $maxHops,
            'output' => $this->cleanOutput($output),
            'hops' => $this->parseHops($output),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function validTarget(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) return true;
        return (bool) preg_match('/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/', $host);
    }

    private function commandExists(string $command): bool
    {
        $process = new Process(['sh', '-c', 'command -v ' . escapeshellarg($command)]);
        $process->setTimeout(2);
        $process->run();
        return $process->isSuccessful();
    }

    private function cleanOutput(string $output): string
    {
        return mb_substr($output, 0, 30000);
    }

    private function parsePingSummary(string $output): array
    {
        $result = ['transmitted' => null, 'received' => null, 'packet_loss_percent' => null, 'min_ms' => null, 'avg_ms' => null, 'max_ms' => null];
        if (preg_match('/(\d+) packets transmitted, (\d+) (?:packets )?received,\s*([\d.]+)% packet loss/i', $output, $m)) {
            $result['transmitted'] = (int) $m[1]; $result['received'] = (int) $m[2]; $result['packet_loss_percent'] = (float) $m[3];
        }
        if (preg_match('/=\s*([\d.]+)\/([\d.]+)\/([\d.]+)(?:\/([\d.]+))?\s*ms/', $output, $m)) {
            $result['min_ms'] = (float) $m[1]; $result['avg_ms'] = (float) $m[2]; $result['max_ms'] = (float) $m[3];
        }
        return $result;
    }

    private function parseHops(string $output): array
    {
        $hops = [];
        foreach (preg_split('/\R/', $output) as $line) {
            if (preg_match('/^\s*(\d+)\s+(.*)$/', trim($line), $m)) {
                $rest = trim($m[2]);
                preg_match_all('/(?:\d{1,3}\.){3}\d{1,3}|(?:[0-9a-fA-F]{0,4}:){2,}[0-9a-fA-F:.]+/', $rest, $ips);
                preg_match_all('/([\d.]+)\s*ms/', $rest, $times);
                $hops[] = ['hop' => (int) $m[1], 'addresses' => array_values(array_unique($ips[0] ?? [])), 'latencies_ms' => array_map('floatval', $times[1] ?? []), 'raw' => $rest];
            }
        }
        return $hops;
    }
}
