<?php

namespace HashtagCms\Workflows\Tests\Unit;

use HashtagCms\Workflows\Contracts\WorkflowIdentityResolver;
use HashtagCms\Workflows\Identity\AuthIdentityResolver;
use HashtagCms\Workflows\Identity\Sso\OpaqueTokenSsoDriver;
use HashtagCms\Workflows\Identity\Sso\SsoProviderRepository;
use HashtagCms\Workflows\Identity\SsoIdentityResolver;
use HashtagCms\Workflows\Models\WorkflowSsoProvider;
use HashtagCms\Workflows\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SsoIdentityResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('workflow_sso_providers')) {
            Schema::create('workflow_sso_providers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('site_id')->default(1);
                $table->string('name');
                $table->string('alias');
                $table->text('description')->nullable();
                $table->string('driver')->default('opaque');
                $table->boolean('enabled')->default(true);
                $table->json('config')->nullable();
                $table->string('on_failure')->default('reject');
                $table->unsignedInteger('cache_ttl')->default(300);
                $table->boolean('publish_status')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    private function opaqueProvider(array $overrides = []): WorkflowSsoProvider
    {
        return WorkflowSsoProvider::create(array_merge([
            'site_id' => 1,
            'name' => 'XYZ SSO',
            'alias' => 'xyzsite-sso',
            'driver' => 'opaque',
            'enabled' => true,
            'publish_status' => true,
            'cache_ttl' => 300,
            'on_failure' => 'reject',
            'config' => [
                'verify' => [
                    'url' => 'http://xyzsite.in/sso/authenticate',
                    'method' => 'POST',
                    'headers' => ['Accept' => 'application/json'],
                    'body' => ['token' => '{{request.bearer_token}}'],
                ],
                'identity' => [
                    'user_id' => '{{response.body.data.user.id}}',
                    'claims' => [
                        'email' => '{{response.body.data.user.email}}',
                        'roles' => '{{response.body.data.user.roles}}',
                    ],
                ],
            ],
        ], $overrides));
    }

    private function requestWithToken(?string $token, int $siteId = 1): Request
    {
        $request = Request::create('/api/workflows/execute', 'POST', ['site_id' => $siteId]);
        if ($token !== null) {
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $request;
    }

    private function requestWithHeader(string $header, string $value, int $siteId = 1): Request
    {
        $request = Request::create('/api/workflows/execute', 'POST', ['site_id' => $siteId]);
        $request->headers->set($header, $value);

        return $request;
    }

    private function providerWithCredentialHeader(array $credential): WorkflowSsoProvider
    {
        $provider = $this->opaqueProvider();
        $config = $provider->config;
        $config['credential'] = $credential;
        $provider->config = $config;
        $provider->save();

        return $provider;
    }

    private function fakeSuccess(): void
    {
        Http::fake([
            'xyzsite.in/sso/authenticate' => Http::response([
                'data' => ['user' => [
                    'id' => 'auth0|abc-123',
                    'email' => 'buyer@xyz.in',
                    'roles' => ['buyer', 'member'],
                ]],
            ], 200),
        ]);
    }

    // --- OpaqueTokenSsoDriver -------------------------------------------------

    public function test_opaque_driver_maps_a_verified_token_to_an_external_identity(): void
    {
        $this->fakeSuccess();
        $driver = $this->app->make(OpaqueTokenSsoDriver::class);

        $identity = $driver->resolve($this->opaqueProvider(), $this->requestWithToken('tok-1'));

        $this->assertTrue($identity->isAuthenticated());
        $this->assertNull($identity->localUserId());
        $this->assertSame('auth0|abc-123', $identity->externalUserId());
        $this->assertSame(['buyer', 'member'], $identity->claim('roles'));
        $this->assertSame('buyer@xyz.in', $identity->claim('email'));
        $this->assertSame('xyzsite-sso', $identity->provider);
    }

    public function test_opaque_driver_populates_raw_only_when_mapped(): void
    {
        $this->fakeSuccess();

        // Without a `raw` mapping, raw stays empty (opt-in).
        $curated = $this->app->make(OpaqueTokenSsoDriver::class)
            ->resolve($this->opaqueProvider(), $this->requestWithToken('tok-a'));
        $this->assertSame([], $curated->raw);

        // With `identity.raw` mapped, the full validator payload is carried
        // through — alongside the curated claims, not replacing them.
        $provider = $this->opaqueProvider(['alias' => 'with-raw']);
        $config = $provider->config;
        $config['identity']['raw'] = '{{ response.body.data }}';
        $provider->config = $config;
        $provider->save();

        $identity = $this->app->make(OpaqueTokenSsoDriver::class)
            ->resolve($provider, $this->requestWithToken('tok-b'));

        $this->assertSame('buyer@xyz.in', $identity->raw['user']['email'] ?? null);
        $this->assertSame(['buyer', 'member'], $identity->claim('roles')); // curated claims still present
    }

    public function test_opaque_driver_forwards_the_token_in_the_verify_body(): void
    {
        $this->fakeSuccess();
        $this->app->make(OpaqueTokenSsoDriver::class)
            ->resolve($this->opaqueProvider(), $this->requestWithToken('tok-xyz'));

        Http::assertSent(function ($request) {
            return $request->url() === 'http://xyzsite.in/sso/authenticate'
                && ($request->data()['token'] ?? null) === 'tok-xyz';
        });
    }

    public function test_opaque_driver_caches_and_does_not_reintrospect(): void
    {
        $this->fakeSuccess();
        $driver = $this->app->make(OpaqueTokenSsoDriver::class);
        $provider = $this->opaqueProvider();

        $driver->resolve($provider, $this->requestWithToken('same-token'));
        $driver->resolve($provider, $this->requestWithToken('same-token'));

        Http::assertSentCount(1);
    }

    public function test_opaque_driver_rejects_when_introspection_fails(): void
    {
        Http::fake(['xyzsite.in/sso/authenticate' => Http::response(['error' => 'invalid'], 401)]);
        $driver = $this->app->make(OpaqueTokenSsoDriver::class);

        $identity = $driver->resolve($this->opaqueProvider(), $this->requestWithToken('bad'));

        $this->assertTrue($identity->failed);
        $this->assertFalse($identity->isAuthenticated());
    }

    public function test_opaque_driver_is_anonymous_without_a_token(): void
    {
        $identity = $this->app->make(OpaqueTokenSsoDriver::class)
            ->resolve($this->opaqueProvider(), $this->requestWithToken(null));

        $this->assertTrue($identity->isAnonymous());
        Http::assertNothingSent();
    }

    public function test_opaque_driver_reads_token_from_a_configured_credential_header(): void
    {
        $this->fakeSuccess();
        // Token in a custom header with a prefix — the pattern used by APIs that
        // don't use Authorization (e.g. `sessiontoken: Bearer <token>`).
        $provider = $this->providerWithCredentialHeader(['header' => 'sessiontoken', 'strip_prefix' => 'Bearer ']);
        $request = $this->requestWithHeader('sessiontoken', 'Bearer custom-tok-9');

        $identity = $this->app->make(OpaqueTokenSsoDriver::class)->resolve($provider, $request);

        $this->assertSame('auth0|abc-123', $identity->externalUserId());
        // The prefix was stripped and the bare token forwarded to verify.
        Http::assertSent(fn ($r) => ($r->data()['token'] ?? null) === 'custom-tok-9');
    }

    public function test_configured_credential_header_does_not_fall_back_to_authorization(): void
    {
        $this->fakeSuccess();
        $provider = $this->providerWithCredentialHeader(['header' => 'sessiontoken']);

        // Authorization is present, but the configured header is absent -> no
        // credential, so the verify endpoint is never called.
        $identity = $this->app->make(OpaqueTokenSsoDriver::class)
            ->resolve($provider, $this->requestWithToken('ignored-auth-token'));

        $this->assertTrue($identity->isAnonymous());
        Http::assertNothingSent();
    }

    public function test_credential_header_without_prefix_uses_the_raw_value(): void
    {
        $this->fakeSuccess();
        $provider = $this->providerWithCredentialHeader(['header' => 'sessiontoken']);
        $request = $this->requestWithHeader('sessiontoken', 'raw-token-no-prefix');

        $this->app->make(OpaqueTokenSsoDriver::class)->resolve($provider, $request);

        Http::assertSent(fn ($r) => ($r->data()['token'] ?? null) === 'raw-token-no-prefix');
    }

    // --- SsoProviderRepository ------------------------------------------------

    public function test_repository_prefers_site_over_master_and_skips_disabled(): void
    {
        $master = (int) config('hashtagcms-workflows.master_site_id', 1);
        $this->opaqueProvider(['site_id' => $master, 'alias' => 'master-sso']);
        $this->opaqueProvider(['site_id' => 2, 'alias' => 'site2-sso']);
        $this->opaqueProvider(['site_id' => 2, 'alias' => 'site2-disabled', 'enabled' => false]);

        $repo = new SsoProviderRepository();

        $this->assertSame('site2-sso', $repo->forSite(2)->alias);
        $this->assertSame('master-sso', $repo->forSite(999)->alias);
        $this->assertTrue($repo->anyEnabled());
    }

    // --- SsoIdentityResolver --------------------------------------------------

    public function test_resolver_falls_back_to_local_when_no_provider(): void
    {
        $resolver = $this->app->make(SsoIdentityResolver::class);

        $identity = $resolver->resolve($this->requestWithToken('whatever'));

        // No provider rows -> defers to the local guard -> anonymous (logged out).
        $this->assertTrue($identity->isAnonymous());
        Http::assertNothingSent();
    }

    public function test_resolver_returns_the_verified_sso_identity(): void
    {
        $this->fakeSuccess();
        $this->opaqueProvider();
        $resolver = $this->app->make(SsoIdentityResolver::class);

        $identity = $resolver->resolve($this->requestWithToken('tok-1'));

        $this->assertSame('auth0|abc-123', $identity->externalUserId());
        $this->assertSame('xyzsite-sso', $identity->provider);
    }

    public function test_resolver_keeps_failed_marker_under_reject_policy(): void
    {
        Http::fake(['xyzsite.in/sso/authenticate' => Http::response([], 401)]);
        $this->opaqueProvider(['on_failure' => 'reject']);
        $resolver = $this->app->make(SsoIdentityResolver::class);

        $identity = $resolver->resolve($this->requestWithToken('bad'));

        $this->assertTrue($identity->failed);
    }

    public function test_resolver_downgrades_to_anonymous_under_anonymous_policy(): void
    {
        Http::fake(['xyzsite.in/sso/authenticate' => Http::response([], 401)]);
        $this->opaqueProvider(['on_failure' => 'anonymous']);
        $resolver = $this->app->make(SsoIdentityResolver::class);

        $identity = $resolver->resolve($this->requestWithToken('bad'));

        $this->assertTrue($identity->isAnonymous());
        $this->assertFalse($identity->failed);
    }

    public function test_repository_byAlias_resolves_a_specific_enabled_provider(): void
    {
        $this->opaqueProvider(['alias' => 'a-sso']);
        $this->opaqueProvider(['alias' => 'b-sso']);
        $this->opaqueProvider(['alias' => 'c-disabled', 'enabled' => false]);

        $repo = new SsoProviderRepository();

        $this->assertSame('b-sso', $repo->byAlias('b-sso', 1)->alias);
        $this->assertNull($repo->byAlias('c-disabled', 1), 'disabled provider is not resolvable');
        $this->assertNull($repo->byAlias('does-not-exist', 1), 'unknown alias resolves to null');
    }

    public function test_repository_forSite_is_deterministic_with_multiple_providers(): void
    {
        // Two enabled providers on the same site: forSite must pick one stably.
        $first = $this->opaqueProvider(['alias' => 'first-sso']);
        $this->opaqueProvider(['alias' => 'second-sso']);

        $repo = new SsoProviderRepository();

        $this->assertSame($first->alias, $repo->forSite(1)->alias);
        $this->assertCount(2, $repo->listForSite(1));
    }

    public function test_resolver_uses_the_pinned_provider_over_the_site_default(): void
    {
        Http::fake([
            'xyzsite.in/sso/authenticate' => Http::response(['data' => ['user' => ['id' => 'ext|default']]], 200),
            'other.in/sso' => Http::response(['data' => ['user' => ['id' => 'ext|pinned']]], 200),
        ]);

        // Site default (lowest id) verifies against xyzsite.in -> ext|default.
        $this->opaqueProvider(['alias' => 'default-sso']);
        // Pinned provider verifies against other.in -> ext|pinned.
        $pinned = $this->opaqueProvider(['alias' => 'pinned-sso']);
        $config = $pinned->config;
        $config['verify']['url'] = 'http://other.in/sso';
        $pinned->config = $config;
        $pinned->save();

        $resolver = $this->app->make(SsoIdentityResolver::class);

        // Without a pin -> site default.
        $this->assertSame('ext|default', $resolver->resolve($this->requestWithToken('t'))->externalUserId());
        // Pinned to 'pinned-sso' -> that provider is used.
        $this->assertSame('ext|pinned', $resolver->resolve($this->requestWithToken('t'), 'pinned-sso')->externalUserId());
    }

    public function test_resolver_ignores_sso_when_pinned_to_none(): void
    {
        // A provider IS enabled for the site, but the workflow opts out via the
        // "none" sentinel -> resolve via the local guard, never calling verify.
        $this->fakeSuccess();
        $this->opaqueProvider();
        $resolver = $this->app->make(SsoIdentityResolver::class);

        $identity = $resolver->resolve(
            $this->requestWithToken('tok-1'),
            SsoIdentityResolver::PROVIDER_NONE
        );

        $this->assertTrue($identity->isAnonymous(), 'no local guard user -> anonymous, not the SSO identity');
        Http::assertNothingSent();
    }

    public function test_resolver_falls_back_to_site_default_when_the_pin_is_unknown(): void
    {
        $this->fakeSuccess();
        $this->opaqueProvider(); // xyzsite-sso is the only (default) provider

        $resolver = $this->app->make(SsoIdentityResolver::class);

        // A stale/typo'd pin degrades to the site default rather than failing.
        $identity = $resolver->resolve($this->requestWithToken('tok-1'), 'no-such-provider');

        $this->assertSame('xyzsite-sso', $identity->provider);
        $this->assertSame('auth0|abc-123', $identity->externalUserId());
    }

    // --- container swap (service provider binding) ----------------------------

    public function test_contract_binds_to_sso_resolver_when_a_provider_is_enabled(): void
    {
        $this->opaqueProvider();

        $this->assertInstanceOf(
            SsoIdentityResolver::class,
            $this->app->make(WorkflowIdentityResolver::class)
        );
    }

    public function test_contract_stays_local_when_table_present_but_no_enabled_provider(): void
    {
        $this->opaqueProvider(['enabled' => false]);

        $this->assertInstanceOf(
            AuthIdentityResolver::class,
            $this->app->make(WorkflowIdentityResolver::class)
        );
    }
}
