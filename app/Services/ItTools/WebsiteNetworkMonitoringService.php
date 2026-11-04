<?php

namespace App\Services\ItTools;

use App\Models\Service;
use Carbon\Carbon;

class WebsiteNetworkMonitoringService extends NetworkMonitoringService
{
    /**
     * Website SSL detection must use the same real TLS/SNI implementation
     * as IT Tools > Check Domain so both screens return the same certificate.
     */
    public function detectSsl(Service $service, ?string $target = null): array
    {
        $service->loadMissing('serviceType');

        if (strtoupper((string) $service->serviceType?->code) !== 'WEBSITE') {
            return [
                'ssl_detected' => false,
                'error' => 'SSL detection is available only for Website services.',
            ];
        }

        $host = $this->normalizeWebsiteHost((string) ($target ?? $service->monitor_target ?: $service->value));

        if (!$host || filter_var($host, FILTER_VALIDATE_IP)) {
            $this->saveWebsiteSslState($service, false, null, null, null, 'error');

            return [
                'ssl_detected' => false,
                'error' => 'A valid website hostname is required for SSL detection.',
            ];
        }

        try {
            $result = app(SslAuditService::class)->check($host, 443);

            if (($result['status'] ?? 'error') !== 'ok' || empty($result['valid_to'])) {
                $error = (string) ($result['error'] ?? 'HTTPS certificate could not be detected.');
                $this->saveWebsiteSslState($service, false, null, null, null, 'error');

                return [
                    'ssl_detected' => false,
                    'error' => $error,
                    'host' => $host,
                ];
            }

            $validFrom = !empty($result['valid_from'])
                ? Carbon::parse($result['valid_from'])
                : null;
            $expiry = Carbon::parse($result['valid_to']);
            $issuer = (string) ($result['issuer'] ?? $result['vendor'] ?? '');
            $subject = (string) ($result['subject'] ?? '');
            $status = $expiry->isPast() ? 'expired' : 'valid';
            $daysRemaining = (int) floor(($expiry->timestamp - now()->timestamp) / 86400);

            $this->saveWebsiteSslState($service, true, $validFrom, $expiry, $issuer ?: null, $status);

            return [
                'ssl_detected' => true,
                'ssl_status' => $status,
                'issuer' => $issuer ?: null,
                'vendor' => $result['vendor'] ?? null,
                'subject_cn' => $subject ?: null,
                'valid_from' => $validFrom?->toDateString(),
                'expires_at' => $expiry->toIso8601String(),
                'saved_ssl_expiry_date' => $expiry->toDateString(),
                'days_remaining' => $daysRemaining,
                'tls_version' => $result['tls_version'] ?? null,
                'cipher' => $result['cipher'] ?? null,
                'hostname_match' => (bool) ($result['verify'] ?? false),
                'host' => $host,
            ];
        } catch (\Throwable $e) {
            $this->saveWebsiteSslState($service, false, null, null, null, 'error');

            return [
                'ssl_detected' => false,
                'error' => mb_substr($e->getMessage(), 0, 1000),
                'host' => $host,
            ];
        }
    }

    private function normalizeWebsiteHost(string $target): ?string
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

    private function saveWebsiteSslState(
        Service $service,
        bool $detected,
        ?Carbon $validFrom,
        ?Carbon $expiry,
        ?string $issuer,
        string $status
    ): void {
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
}
