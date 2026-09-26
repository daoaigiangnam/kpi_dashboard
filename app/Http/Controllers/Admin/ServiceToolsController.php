<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\ItTools\DomainAuditService;
use App\Services\ItTools\ServiceAlertEmailService;
use App\Services\ItTools\ServiceAlertEngine;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class ServiceToolsController extends Controller
{
    public function detectDomainExpiry(
        Request $request,
        Service $service,
        DomainAuditService $domainAudit,
        ServiceAlertEngine $alertEngine,
        ServiceAlertEmailService $emailService
    ) {
        abort_unless($service->isVisibleTo(auth()->user()), 403);
        $service->loadMissing(['serviceType', 'alertPolicy']);
        $code = strtoupper((string) $service->serviceType?->code);

        if ($request->boolean('ssl')) {
            if ($code !== 'WEBSITE') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Detect SSL is available only for Website services.',
                ], 422);
            }

            $target = trim((string) $request->input(
                'monitor_target',
                $service->monitor_target ?: $service->value
            ));

            $result = $this->detectWebsiteSsl($target);

            if (!empty($result['ssl_detected'])) {
                $service->ssl_detected = true;
                $service->ssl_valid_from_date = $result['valid_from'];
                $service->ssl_expiry_date = $result['saved_ssl_expiry_date'];
                $service->ssl_issuer = $result['issuer'];
                $service->ssl_status = $result['ssl_status'];
                $service->ssl_last_checked_at = now();
                $service->save();

                $event = $alertEngine->evaluateSsl($service->fresh(['alertPolicy']));
                if ($event) {
                    $emailService->notifyNewAlert($event);
                }
            } else {
                $service->ssl_last_checked_at = now();
                $service->ssl_status = 'error';
                $service->save();
            }

            return response()->json([
                'ok' => !empty($result['ssl_detected']),
                'result' => $result,
                'message' => !empty($result['ssl_detected'])
                    ? 'SSL certificate detected and saved.'
                    : ($result['error'] ?? 'SSL certificate not detected.'),
            ], !empty($result['ssl_detected']) ? 200 : 422);
        }

        if ($code !== 'DOMAIN') {
            return response()->json([
                'ok' => false,
                'message' => 'Detect Expiry is available only for Domain services.',
            ], 422);
        }

        $domain = trim((string) ($service->value ?: $service->service_name));
        $result = $domainAudit->check($domain);

        if (!empty($result['expires_at'])) {
            try {
                $service->expiry_date = \Carbon\Carbon::parse($result['expires_at'])->toDateString();
                $service->save();
                $result['saved_expiry_date'] = $service->expiry_date->format('Y-m-d');
            } catch (\Throwable) {
                // Return detected value even if conversion fails.
            }
        }

        return response()->json(['ok' => !empty($result['expires_at']), 'result' => $result]);
    }

    private function detectWebsiteSsl(string $target): array
    {
        $host = trim($target);
        $host = preg_replace('/^https?:\/\//i', '', $host);
        $host = trim(explode('/', $host, 2)[0]);
        $host = strtolower(rtrim($host, '.'));

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return ['ssl_detected' => false, 'error' => 'A valid website hostname is required.'];
        }

        $process = new Process([
            'openssl', 's_client',
            '-connect', $host . ':443',
            '-servername', $host,
            '-showcerts',
            '-brief',
        ]);
        $process->setTimeout(20);
        $process->run();
        $output = $process->getOutput() . "\n" . $process->getErrorOutput();

        if (!preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $output, $m)) {
            $lines = array_values(array_filter(preg_split('/\R+/', trim($output)), fn ($line) => preg_match('/(error|fail|verify|connect|handshake|certificate)/i', $line)));
            return [
                'ssl_detected' => false,
                'error' => 'Không lấy được certificate HTTPS.' . ($lines ? ' ' . implode(' | ', array_slice($lines, 0, 3)) : ''),
            ];
        }

        $pem = "-----BEGIN CERTIFICATE-----" . $m[1] . "-----END CERTIFICATE-----";
        $parsed = @openssl_x509_parse($pem);
        if (!$parsed) {
            return ['ssl_detected' => false, 'error' => 'Certificate HTTPS nhận được nhưng không thể phân tích.'];
        }

        $validFrom = isset($parsed['validFrom_time_t']) ? \Carbon\Carbon::createFromTimestamp((int) $parsed['validFrom_time_t']) : null;
        $expiry = isset($parsed['validTo_time_t']) ? \Carbon\Carbon::createFromTimestamp((int) $parsed['validTo_time_t']) : null;
        if (!$expiry) {
            return ['ssl_detected' => false, 'error' => 'Certificate không có ngày hết hạn hợp lệ.'];
        }

        $issuer = $parsed['issuer']['O'] ?? ($parsed['issuer']['CN'] ?? null);
        $subject = $parsed['subject']['CN'] ?? null;
        $days = now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false);

        return [
            'ssl_detected' => true,
            'ssl_status' => $expiry->isPast() ? 'expired' : 'valid',
            'issuer' => $issuer,
            'subject_cn' => $subject,
            'valid_from' => $validFrom?->toDateString(),
            'saved_ssl_expiry_date' => $expiry->toDateString(),
            'expires_at' => $expiry->toIso8601String(),
            'days_remaining' => $days,
            'san' => isset($parsed['extensions']['subjectAltName'])
                ? array_map('trim', preg_split('/\s*,\s*/', $parsed['extensions']['subjectAltName']))
                : [],
        ];
    }

    public function export(Request $request)
    {
        $user = auth()->user();
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $services = Service::query()
            ->visibleTo($user)
            ->with(['customer', 'serviceType', 'provider', 'alertPolicy', 'responsibleIt'])
            ->when($search !== '', fn ($q) => $q->where(fn ($x) => $x
                ->where('service_name', 'like', "%{$search}%")
                ->orWhere('value', 'like', "%{$search}%")
                ->orWhere('monitor_target', 'like', "%{$search}%")
            ))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderBy('customer_id')->orderBy('service_name')->get();

        return response()->streamDownload(function () use ($services) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Customer','Service Name','Service Type','Value','Provider','Cost','Currency','Billing Cycle',
                'Term (months)','Expiry Date','Alert Policy','Responsible IT','Status','Auto Renew','WAN IP / Target',
                'Check Method','Check Port','Interval (sec)','Timeout (sec)','Monitor Status','Latency (ms)',
                'Packet Loss (%)','Failure Count','Last Check','Down Since','Note'
            ]);
            foreach ($services as $s) {
                fputcsv($out, [
                    $s->customer?->name, $s->service_name, $s->serviceType?->name, $s->value, $s->provider?->name,
                    $s->cost_amount, $s->cost_currency, $s->cost_billing_cycle, $s->service_term_months,
                    optional($s->expiry_date)->format('Y-m-d'), $s->alertPolicy?->name, $s->responsibleIt?->name,
                    $s->status, $s->auto_renew ? 'Yes' : 'No', $s->monitor_target,
                    strtoupper((string) $s->monitor_check_method), $s->monitor_port, $s->monitor_interval_seconds,
                    $s->monitor_timeout_seconds, $s->monitor_status, $s->monitor_last_latency_ms,
                    $s->monitor_packet_loss_percent, $s->monitor_failure_count,
                    optional($s->monitor_last_checked_at)->format('Y-m-d H:i:s'),
                    optional($s->monitor_down_since)->format('Y-m-d H:i:s'), $s->note,
                ]);
            }
            fclose($out);
        }, 'it_services_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
