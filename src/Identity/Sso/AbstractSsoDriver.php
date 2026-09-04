<?php

namespace HashtagCms\Workflows\Identity\Sso;

use HashtagCms\Workflows\Contracts\SsoDriverInterface;
use HashtagCms\Workflows\Engine\VariableInterpolator;
use HashtagCms\Workflows\Engine\WorkflowIdentity;
use Illuminate\Http\Request;

/**
 * Shared plumbing for SSO drivers: pulling the credential off the request,
 * exposing it to config interpolation, and mapping a verified payload through a
 * provider's `identity` block into a {@see WorkflowIdentity}.
 */
abstract class AbstractSsoDriver implements SsoDriverInterface
{
    /**
     * The credential presented by the client, or null when absent.
     *
     * By default this is the standard `Authorization: Bearer <token>` header.
     * Many real APIs carry the token in a different header (e.g. `sessiontoken`),
     * sometimes with a prefix — a provider can point at it via a `credential`
     * block in its config:
     *
     *   "credential": { "header": "sessiontoken", "strip_prefix": "Bearer " }
     *
     * When `credential.header` is set, that header is read (and the optional
     * `strip_prefix` removed); otherwise it falls back to `Authorization` bearer.
     *
     * @param array<string, mixed> $config The provider's config array.
     */
    protected function credential(?Request $request, array $config = []): ?string
    {
        if ($request === null) {
            return null;
        }

        $cred = $config['credential'] ?? null;
        if (is_array($cred) && ! empty($cred['header'])) {
            $value = $request->header($cred['header']);
            if ($value === null) {
                return null;
            }

            $prefix = $cred['strip_prefix'] ?? null;
            if (is_string($prefix) && $prefix !== '' && str_starts_with($value, $prefix)) {
                $value = substr($value, strlen($prefix));
            }

            $value = trim($value);

            return $value !== '' ? $value : null;
        }

        // Default: standard Authorization: Bearer <token>.
        return $request->bearerToken();
    }

    /**
     * Interpolation source for the verify request: `{{request.bearer_token}}`,
     * `{{request.headers.*}}`, `{{request.query.*}}`.
     */
    protected function requestContext(?Request $request, ?string $token): array
    {
        return [
            'request' => [
                'bearer_token' => $token,
                'token' => $token,
                'headers' => $request ? array_map(
                    fn ($v) => is_array($v) ? ($v[0] ?? null) : $v,
                    $request->headers->all()
                ) : [],
                'query' => $request ? $request->query() : [],
            ],
        ];
    }

    /**
     * Map a verified payload (available to interpolation as `$context`) through the
     * provider's `identity` block into a normalized identity.
     *
     * External subjects are stringified so they route to `external_user_id`
     * (see WorkflowIdentity), never masquerading as a local integer user id.
     */
    protected function buildIdentity(array $identityBlock, array $context, string $provider): WorkflowIdentity
    {
        $rawId = VariableInterpolator::interpolate($identityBlock['user_id'] ?? null, $context);

        if ($rawId === null || $rawId === '') {
            // Verified, but the configured user_id path resolved to nothing —
            // treat as a mapping failure rather than a silent anonymous.
            return WorkflowIdentity::rejected($provider);
        }

        $claims = VariableInterpolator::interpolate($identityBlock['claims'] ?? [], $context);

        // Opt-in raw passthrough: only populated when the provider maps `raw`
        // (e.g. "raw": "{{ response.body.data }}"). Exposed as {{ identity.raw.* }}.
        $raw = array_key_exists('raw', $identityBlock)
            ? VariableInterpolator::interpolate($identityBlock['raw'], $context)
            : [];

        return new WorkflowIdentity(
            id: (string) $rawId,
            claims: is_array($claims) ? $claims : [],
            provider: $provider,
            raw: is_array($raw) ? $raw : [],
        );
    }
}
