<?php

namespace HashtagCms\Workflows\Engine\TargetAdapters;

use Illuminate\Support\Facades\Http;
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
}
