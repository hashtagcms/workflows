<?php

namespace HashtagCms\Workflows\Identity\Sso;

use HashtagCms\Workflows\Engine\TargetAdapters\HttpTargetAdapter;
use HashtagCms\Workflows\Engine\WorkflowIdentity;
use HashtagCms\Workflows\Models\WorkflowSsoProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Opaque-token driver: the client's token means nothing to us, so we introspect
 * it against the login service and map the response to an identity.
 *
 * The provider's `config.verify` block IS the request formatter — it reuses the
 * exact HttpTargetAdapter shape (url/method/headers/body with `{{request.*}}`
 * interpolation), so a token is forwarded to e.g. https://login.example.com/sso/authenticate.
 * The `config.identity` block is the response formatter, mapping the returned
 * body to `{ user_id, claims }` via `{{response.body.*}}`.
 *
 * Successful resolutions are cached per token (hashed) for `cache_ttl` seconds so
 * a burst of calls from one client doesn't hammer the login service. Failures are
 * never cached.
 */
class OpaqueTokenSsoDriver extends AbstractSsoDriver
{
    public function __construct(private HttpTargetAdapter $http) {}

    public function resolve(WorkflowSsoProvider $provider, ?Request $request): WorkflowIdentity
    {
        $config = $provider->config ?? [];

        $token = $this->credential($request, $config);
        if ($token === null || $token === '') {
            return WorkflowIdentity::anonymous();
        }

        $verify = $config['verify'] ?? null;
        if (empty($verify['url'])) {
            // Misconfigured provider — no endpoint to introspect against.
            return WorkflowIdentity::rejected($provider->alias);
        }

        $cacheKey = 'wf_sso:' . $provider->alias . ':' . hash('sha256', $token);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return new WorkflowIdentity(
                id: $cached['id'],
                claims: $cached['claims'] ?? [],
                provider: $provider->alias,
                raw: $cached['raw'] ?? [],
            );
        }

        $requestContext = $this->requestContext($request, $token);

        $result = $this->http->execute(['http' => $verify], $requestContext);
        if (! ($result['success'] ?? false)) {
            return WorkflowIdentity::rejected($provider->alias);
        }

        $mappingContext = $requestContext + [
            'response' => [
                'body' => $result['body'] ?? null,
                'status' => $result['status'] ?? null,
                'headers' => $result['headers'] ?? [],
            ],
        ];

        $identity = $this->buildIdentity($config['identity'] ?? [], $mappingContext, $provider->alias);

        if ($identity->isAuthenticated() && ($provider->cache_ttl ?? 0) > 0) {
            Cache::put(
                $cacheKey,
                ['id' => $identity->id, 'claims' => $identity->claims, 'raw' => $identity->raw],
                (int) $provider->cache_ttl
            );
        }

        return $identity;
    }
}
