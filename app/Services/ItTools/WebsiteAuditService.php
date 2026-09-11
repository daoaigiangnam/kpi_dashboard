<?php

namespace App\Services\ItTools;

use Illuminate\Support\Facades\Http;

class WebsiteAuditService
{
    public function check(string $host): array
    {
        $host = trim($host);
        $results = [];
        foreach (['https', 'http'] as $scheme) {
            $url = $scheme . '://' . $host;
            $started = microtime(true);
            try {
                $response = Http::timeout(10)->withOptions(['allow_redirects' => ['track_redirects' => true]])->get($url);
                $results[$scheme] = [
                    'url' => $url, 'status' => $response->status(),
                    'final_url' => $response->effectiveUri()?->__toString(),
                    'response_time_ms' => round((microtime(true) - $started) * 1000, 1),
                    'content_type' => $response->header('Content-Type'),
                    'server' => $response->header('Server'),
                    'hsts' => $response->header('Strict-Transport-Security') !== null,
                    'headers' => $this->securityHeaders($response),
                    'online' => $response->successful() || $response->redirect(),
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                $results[$scheme] = [
                    'url' => $url, 'status' => null, 'final_url' => null,
                    'response_time_ms' => round((microtime(true) - $started) * 1000, 1),
                    'content_type' => null, 'server' => null, 'hsts' => false,
                    'headers' => [], 'online' => false, 'error' => $e->getMessage(),
                ];
            }
        }
        return ['host' => $host, 'http' => $results['http'], 'https' => $results['https']];
    }

    private function securityHeaders($response): array
    {
        $names = [
            'Content-Security-Policy','X-Content-Type-Options','X-Frame-Options',
            'Referrer-Policy','Permissions-Policy','Cross-Origin-Opener-Policy',
        ];
        $out = [];
        foreach ($names as $name) $out[$name] = $response->header($name) ?: null;
        return $out;
    }
}
