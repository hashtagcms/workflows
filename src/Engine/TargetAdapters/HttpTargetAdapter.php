<?php

namespace HashtagCms\Workflows\Engine\TargetAdapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use HashtagCms\Workflows\Engine\VariableInterpolator;

class HttpTargetAdapter implements TargetAdapterInterface
{
    public function execute(array $targetConfig, array $context): array
    {
        $httpConfig = $targetConfig['http'] ?? $targetConfig;

        $rawUrl = $httpConfig['url'] ?? '';
        if (empty($rawUrl)) {
            return [
                'success' => false,
                'status' => 400,
                'body' => null,
                'headers' => [],
                'error' => 'Target HTTP URL is required.'
            ];
        }

        $url = VariableInterpolator::interpolate($rawUrl, $context);
        $this->warnOnSelfCall($url);
        $method = strtoupper($httpConfig['method'] ?? 'POST');
        $headers = VariableInterpolator::interpolate($httpConfig['headers'] ?? [], $context);
        $queryParams = VariableInterpolator::interpolate($httpConfig['query'] ?? [], $context);
        $body = VariableInterpolator::interpolate($httpConfig['body'] ?? [], $context);
        $timeout = (int)($httpConfig['timeout'] ?? 10);

        try {
            $client = Http::timeout($timeout);

            if (!empty($headers)) {
                $client = $client->withHeaders($headers);
            }

            if (!empty($httpConfig['auth'])) {
                $authType = strtolower($httpConfig['auth']['type'] ?? 'bearer');
                if ($authType === 'bearer') {
                    $token = VariableInterpolator::interpolate($httpConfig['auth']['token'] ?? '', $context);
                    $client = $client->withToken($token);
                } elseif ($authType === 'basic') {
                    $username = VariableInterpolator::interpolate($httpConfig['auth']['username'] ?? '', $context);
                    $password = VariableInterpolator::interpolate($httpConfig['auth']['password'] ?? '', $context);
                    $client = $client->withBasicAuth($username, $password);
                }
            }

            if (!empty($queryParams)) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($queryParams);
            }

            $response = match ($method) {
                'GET' => $client->get($url),
                'POST' => $client->post($url, $body),
                'PUT' => $client->put($url, $body),
                'PATCH' => $client->patch($url, $body),
                'DELETE' => $client->delete($url, $body),
                default => $client->send($method, $url, ['json' => $body]),
            };

            $status = $response->status();
            $responseBody = $response->json() ?? $response->body();

            return [
                'success' => $response->successful(),
                'status' => $status,
                'body' => $responseBody,
                'headers' => $response->headers(),
                'error' => $response->successful() ? null : "Target HTTP request returned status {$status}."
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 500,
                'body' => null,
                'headers' => [],
                'error' => 'HTTP Execution Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Warn when a workflow's HTTP target points back at this app's own host.
     * Such self-calls block until they time out under a single-worker dev server
     * (`php artisan serve`); a multi-worker runtime (Octane / php-fpm) is required.
     * We only log — self-calls are legitimate on multi-worker setups.
     */
    private function warnOnSelfCall(string $url): void
    {
        $targetHost = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($targetHost && $appHost && strcasecmp($targetHost, $appHost) === 0) {
            Log::warning(
                "[workflows] HTTP target host '{$targetHost}' is this app itself. Under a " .
                'single-worker dev server (php artisan serve) this self-call blocks until it ' .
                'times out — use a multi-worker runtime (Octane / php-fpm) for such workflows.'
            );
        }
    }
}
