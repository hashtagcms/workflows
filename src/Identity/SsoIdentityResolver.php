<?php

namespace HashtagCms\Workflows\Identity;

use HashtagCms\Workflows\Contracts\SsoDriverInterface;
use HashtagCms\Workflows\Contracts\WorkflowIdentityResolver;
use HashtagCms\Workflows\Engine\WorkflowIdentity;
use HashtagCms\Workflows\Identity\Sso\JwtSsoDriver;
use HashtagCms\Workflows\Identity\Sso\OpaqueTokenSsoDriver;
use HashtagCms\Workflows\Identity\Sso\SsoProviderRepository;
use HashtagCms\Workflows\Models\WorkflowSsoProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * Identity resolver backed by the data-driven SSO provider module.
 *
 * Picks the provider for the request's site (or the specific provider a workflow
 * pins via `sso_provider_alias`), dispatches to the driver named on the provider
 * row, and applies the provider's `on_failure` policy: a rejected credential is
 * downgraded to anonymous when `on_failure = anonymous`, otherwise it is surfaced
 * as a failed identity for the caller (execution wiring) to turn into a 401. When
 * no provider applies, it falls back to the default local resolver so nothing
 * regresses.
 */
class SsoIdentityResolver implements WorkflowIdentityResolver
{
    /**
     * Reserved `sso_provider_alias` value meaning "ignore SSO for this workflow"
     * — resolve identity via the local guard only, even when a provider is active
     * for the site. Uses an `@` (outside the provider alias charset) so it can
     * never collide with a real alias.
     */
    public const PROVIDER_NONE = '@none';

    /** @var array<string, class-string<SsoDriverInterface>> */
    private array $drivers = [
        'opaque' => OpaqueTokenSsoDriver::class,
        'jwt' => JwtSsoDriver::class,
    ];

    public function __construct(
        private SsoProviderRepository $providers,
        private AuthIdentityResolver $fallback,
        private Container $container,
    ) {}

    public function resolve(?Request $request = null, ?string $ssoProviderAlias = null): WorkflowIdentity
    {
        // "None": the workflow opts out of SSO entirely — resolve via the local
        // guard, ignoring any active provider. (For providers created ahead of use.)
        if ($ssoProviderAlias === self::PROVIDER_NONE) {
            return $this->fallback->resolve($request);
        }

        $siteId = $this->siteId($request);

        // A workflow may pin a specific provider by alias; use it when it resolves
        // for this site, otherwise fall back to the site's default provider so a
        // stale/typo'd pin degrades gracefully instead of failing hard.
        $provider = null;
        if ($ssoProviderAlias !== null && $ssoProviderAlias !== '') {
            $provider = $this->providers->byAlias($ssoProviderAlias, $siteId);
        }
        $provider ??= $this->providers->forSite($siteId);

        if ($provider === null) {
            // No SSO configured for this site — behave exactly as the default.
            return $this->fallback->resolve($request);
        }

        $identity = $this->driverFor($provider)->resolve($provider, $request);

        // A rejected credential under an "anonymous" policy runs unauthenticated
        // instead of blocking; "reject" keeps the failed marker for the caller.
        if ($identity->failed && $provider->on_failure === 'anonymous') {
            return WorkflowIdentity::anonymous();
        }

        // No credential at all: let the local guard have a say (e.g. an admin
        // session) before settling on anonymous.
        if ($identity->isAnonymous()) {
            return $this->fallback->resolve($request);
        }

        return $identity;
    }

    private function driverFor(WorkflowSsoProvider $provider): SsoDriverInterface
    {
        $class = $this->drivers[$provider->driver] ?? null;
        if ($class === null) {
            throw new \RuntimeException("Unknown SSO driver '{$provider->driver}' for provider '{$provider->alias}'.");
        }

        return $this->container->make($class);
    }

    private function siteId(?Request $request): int
    {
        $master = (int) config('hashtagcms-workflows.master_site_id', 1);

        $candidate = $request?->input('site_id')
            ?? $request?->header('X-Site-Id')
            ?? $master;

        return (int) $candidate;
    }
}
