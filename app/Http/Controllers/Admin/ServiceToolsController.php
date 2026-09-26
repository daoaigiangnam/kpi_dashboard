<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\ItTools\DomainAuditService;
use App\Services\ItTools\ServiceAlertEmailService;
use App\Services\ItTools\ServiceAlertEngine;
use App\Services\ItTools\SslAuditService;
use Illuminate\Http\Request;

class ServiceToolsController extends Controller
{
    public function detectDomainExpiry(
        Request $request,
        Service $service,
        DomainAuditService $domainAudit,
        ServiceAlertEngine $alertEngine,
        ServiceAlertEmailService $emailService,
        SslAuditService $sslAudit
    ) {
        abort_unless($service->isVisibleTo(auth()->user()), 403);
        $service->loadMissing(['serviceType', 'alertPolicy']);
        $code = strtoupper((string) $service->serviceType?->code);

        // Website SSL detection is intentionally independent from Domain expiry.
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

            $result = $this->detectWebsiteSsl($target, $sslAudit);

            if (!empty($result['ssl_detected'])) {
                $service->ssl_detected = true;
                $service->ssl_valid_from_date = $result['valid_from'] ?? null;
                $service->ssl_expiry_date = $result['saved_ssl_expiry_date'] ?? null;
                $service->ssl_issuer = $result['issuer'] ?? null;
                $service->ssl_status = $result['ssl_status'] ?? 'valid';
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

    /**
     * Detect the real HTTPS certificate using a TLS connection with SNI.
     * Certificate-chain verification is deliberately disabled for discovery:
     * an incomplete server chain must not prevent us from reading the actual
     * certificate and expiry date. The result still exposes hostname matching.
     */
    private function detectWebsiteSsl(string $target, SslAuditService $sslAudit): array
    {
        $result = $sslAudit->check($target, 443);

        if (($result['status'] ?? null) !== 'ok' || empty($result['valid_to'])) {
            return [
                'ssl_detected' => false,
                'error' => $result['error'] ?? 'Không lấy được certificate HTTPS.',
                'tls_details' => [
                    'host' => $result['host'] ?? $target,
                    'verify' => $result['verify'] ?? false,
                    'tls_version' => $result['tls_version'] ?? null,
                    'cipher' => $result['cipher'] ?? null,
                    'elapsed_ms' => $result['elapsed_ms'] ?? null,
                ],
            ];
        }

        return [
            'ssl_detected' => true,
            'ssl_status' => ($result['days_remaining'] ?? 1) < 0 ? 'expired' : 'valid',
            'issuer' => $result['issuer'] ?? null,
            'subject_cn' => $result['subject'] ?? null,
            'valid_from' => !empty($result['valid_from'])
                ? \Carbon\Carbon::parse($result['valid_from'])->toDateString()
                : null,
            'saved_ssl_expiry_date' => \Carbon\Carbon::parse($result['valid_to'])->toDateString(),
            'expires_at' => $result['valid_to'],
            'days_remaining' => $result['days_remaining'] ?? null,
            'san' => $result['san'] ?? [],
            'tls_version' => $result['tls_version'] ?? null,
            'cipher' => $result['cipher'] ?? null,
            'hostname_verified' => (bool) ($result['verify'] ?? false),
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
