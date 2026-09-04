<?php

namespace HashtagCms\Workflows\Identity\Sso;

use HashtagCms\Workflows\Engine\WorkflowIdentity;
use HashtagCms\Workflows\Models\WorkflowSsoProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * JWT driver: the login service issues a signed JWT, so we verify the signature
 * locally against its published JWKS — no per-request call to the login service.
 *
 * The token claims are exposed to the provider's `identity` block as
 * `{{token.*}}` (e.g. `{{token.sub}}`, `{{token.email}}`), the same mapping shape
 * the opaque driver uses for `{{response.body.*}}`.
 *
 * Signature verification is delegated to firebase/php-jwt, an OPTIONAL dependency
 * (see composer.json "suggest"). It is only required when a provider actually uses
 * the `jwt` driver; the opaque driver needs nothing extra. If a `jwt` provider is
 * configured without the library installed, this throws a clear, actionable error
 * rather than silently accepting or rejecting tokens.
 */
class JwtSsoDriver extends AbstractSsoDriver
{
    public function resolve(WorkflowSsoProvider $provider, ?Request $request): WorkflowIdentity
    {
        $config = $provider->config ?? [];

        $token = $this->credential($request, $config);
        if ($token === null || $token === '') {
            return WorkflowIdentity::anonymous();
        }

        if (! class_exists(\Firebase\JWT\JWT::class) || ! class_exists(\Firebase\JWT\JWK::class)) {
            throw new \RuntimeException(
                "SSO provider '{$provider->alias}' uses the jwt driver, which requires the "
                . 'firebase/php-jwt package. Install it with: composer require firebase/php-jwt'
            );
        }

        if (empty($config['jwks_url'])) {
            return WorkflowIdentity::rejected($provider->alias);
        }

        try {
            $keys = \Firebase\JWT\JWK::parseKeySet($this->jwks($config['jwks_url'], $provider));
            $decoded = (array) \Firebase\JWT\JWT::decode($token, $keys);
        } catch (\Throwable $e) {
            // Invalid signature, expired, malformed — a present-but-invalid credential.
            return WorkflowIdentity::rejected($provider->alias);
        }

        if (! $this->claimsMatch($decoded, $config)) {
            return WorkflowIdentity::rejected($provider->alias);
        }

        return $this->buildIdentity(
            $config['identity'] ?? [],
            ['token' => $decoded],
            $provider->alias,
        );
    }

    /** Fetch and cache the provider's JWKS (public keys rotate rarely). */
    private function jwks(string $url, WorkflowSsoProvider $provider): array
    {
        $ttl = max(60, (int) ($provider->cache_ttl ?? 300));

        return Cache::remember('wf_sso_jwks:' . $provider->alias, $ttl, function () use ($url) {
            return Http::timeout(10)->get($url)->json() ?? [];
        });
    }

    /** Enforce issuer/audience when the provider pins them. */
    private function claimsMatch(array $decoded, array $config): bool
    {
        if (! empty($config['issuer']) && ($decoded['iss'] ?? null) !== $config['issuer']) {
            return false;
        }

        if (! empty($config['audience'])) {
            $aud = $decoded['aud'] ?? null;
            $aud = is_array($aud) ? $aud : [$aud];
            if (! in_array($config['audience'], $aud, true)) {
                return false;
            }
        }

        return true;
    }
}
