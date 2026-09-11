<?php

namespace App\Services\IT;

class WebsiteAuditService
{
    public function audit(string $host): array
    {
        $urls = ['https://' . $host, 'http://' . $host];
        $results = [];

        foreach ($urls as $url) {
            $results[$url] = $this->request($url);
        }

        return [
            'status' => true,
            'host' => $host,
            'https' => $results['https://' . $host],
            'http' => $results['http://' . $host],
        ];
    }

    private function request(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'KPI Dashboard IT Outsourcing Tools/1.0',
        ]);

        $start = microtime(true);
        $raw = curl_exec($ch);
        $elapsed = (int) round((microtime(true) - $start) * 1000);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $redirectCount = (int) curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
        $headers = $raw !== false ? substr($raw, 0, $headerSize) : '';
        curl_close($ch);

        $headerMap = $this->headers($headers);

        return [
            'online' => $errno === 0 && $status > 0,
            'status_code' => $status ?: null,
            'response_ms' => $elapsed,
            'final_url' => $finalUrl ?: $url,
            'content_type' => $contentType,
            'redirect_count' => $redirectCount,
            'server' => $headerMap['server'] ?? null,
            'hsts' => isset($headerMap['strict-transport-security']),
            'headers' => $headerMap,
            'error' => $error ?: null,
        ];
    }

    private function headers(string $raw): array
    {
        $headers = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($key))] = trim($value);
        }
        return $headers;
    }
}
