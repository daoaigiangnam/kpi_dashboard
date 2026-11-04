<?php

namespace App\Services\ItTools;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ApiTesterService
{
    public function send(array $input): array
    {
        $method = strtoupper($input['method'] ?? 'GET');
        $url = trim((string) ($input['url'] ?? ''));
        $timeout = min(60, max(1, (int) ($input['timeout'] ?? 15)));
        $verify = (bool) ($input['verify_ssl'] ?? true);
        $follow = (bool) ($input['follow_redirects'] ?? true);
        $headers = $this->cleanMap($input['headers'] ?? []);
        $query = $this->cleanMap($input['query'] ?? []);
        $bodyType = $input['body_type'] ?? 'none';
        $body = $input['body'] ?? null;

        $started = microtime(true);

        try {
            $request = Http::timeout($timeout)
                ->connectTimeout(min(10, $timeout))
                ->withHeaders($headers)
                ->withOptions([
                    'verify' => $verify,
                    'allow_redirects' => $follow ? ['track_redirects' => true, 'max' => 10] : false,
                ]);

            if ($bodyType === 'json' && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $decoded = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
                $request = $request->asJson()->withBody(json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'application/json');
            } elseif ($bodyType === 'form' && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $form = is_array($body) ? $body : $this->parsePairs((string) $body);
                $request = $request->asForm()->withBody(http_build_query($form), 'application/x-www-form-urlencoded');
            } elseif ($bodyType === 'raw' && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $request = $request->withBody((string) $body, $headers['Content-Type'] ?? $headers['content-type'] ?? 'text/plain');
            }

            $response = $request->send($method, $url, ['query' => $query]);
            $elapsed = round((microtime(true) - $started) * 1000, 2);
            $responseHeaders = [];
            foreach ($response->headers() as $key => $values) {
                $responseHeaders[$key] = implode(', ', $values);
            }

            $redirects = [];
            $history = $response->header('X-Guzzle-Redirect-History');
            if ($history) {
                $redirects = array_values(array_filter(array_map('trim', explode(',', $history))));
            }

            $content = $response->body();
            $json = null;
            $trimmed = ltrim($content);
            if ($trimmed !== '' && (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '['))) {
                $json = json_decode($content, true);
            }

            return [
                'ok' => true,
                'status' => $response->status(),
                'reason' => $response->reason(),
                'response_time_ms' => $elapsed,
                'response_size' => strlen($content),
                'headers' => $responseHeaders,
                'body' => $content,
                'json' => $json,
                'redirects' => $redirects,
                'final_url' => $response->effectiveUri()?->__toString() ?? $url,
                'request' => [
                    'method' => $method,
                    'url' => $url,
                    'headers' => $headers,
                    'query' => $query,
                    'body_type' => $bodyType,
                ],
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'reason' => null,
                'response_time_ms' => round((microtime(true) - $started) * 1000, 2),
                'response_size' => 0,
                'headers' => [],
                'body' => '',
                'json' => null,
                'redirects' => [],
                'final_url' => $url,
                'error' => Str::limit($e->getMessage(), 1000, ''),
                'request' => [
                    'method' => $method,
                    'url' => $url,
                    'headers' => $headers,
                    'query' => $query,
                    'body_type' => $bodyType,
                ],
                'checked_at' => now()->toIso8601String(),
            ];
        }
    }

    private function cleanMap($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $key => $item) {
            $key = trim((string) $key);
            if ($key === '') continue;
            if (is_scalar($item)) $out[$key] = (string) $item;
        }
        return $out;
    }

    private function parsePairs(string $value): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', trim($value)) ?: [] as $line) {
            if (!str_contains($line, '=')) continue;
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            if ($key !== '') $out[$key] = trim($val);
        }
        return $out;
    }
}
