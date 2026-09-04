<?php

namespace HashtagCms\Workflows\Tests\Unit;

use HashtagCms\Workflows\Workflows;
use HashtagCms\Workflows\Contracts\WorkflowIdentityResolver;
use HashtagCms\Workflows\Engine\WorkflowIdentity;
use HashtagCms\Workflows\Identity\AuthIdentityResolver;
use HashtagCms\Workflows\Models\Workflow;
use HashtagCms\Workflows\Tests\Fixtures\CapturingHandler;
use HashtagCms\Workflows\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

/**
 * Covers the identity-resolution seam added to Workflows::execute():
 * an explicit identity wins, otherwise the bound resolver decides, and the
 * default resolver reproduces the old auth() behaviour.
 */
class WorkflowsIdentityResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CapturingHandler::$userId = null;
        CapturingHandler::$user = null;
        CapturingHandler::$externalUserId = null;
        CapturingHandler::$claims = [];
        CapturingHandler::$identity = null;

        // Negotiation would query workflow_directives; not relevant to identity.
        config(['hashtagcms-workflows.negotiation.enabled' => false]);

        // The real execute() looks up the workflows table and writes a
        // workflow_logs row. Create just those two so the resolution path runs
        // end-to-end without the full CMS-core migration set.
        if (! Schema::hasTable('workflows')) {
            Schema::create('workflows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('site_id')->default(1);
                $table->string('name');
                $table->string('alias');
                $table->text('description')->nullable();
                $table->boolean('auth_required')->default(false);
                $table->string('sso_provider_alias')->nullable();
                $table->string('handler')->nullable();
                $table->json('config')->nullable();
                $table->boolean('publish_status')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('workflow_logs')) {
            Schema::create('workflow_logs', function (Blueprint $table) {
                $table->id();
                $table->string('workflow_alias');
                $table->unsignedBigInteger('site_id')->default(1);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('external_user_id')->nullable();
                $table->string('sso_provider_alias')->nullable();
                $table->json('payload')->nullable();
                $table->json('response_directives')->nullable();
                $table->boolean('is_success')->default(true);
                $table->string('error_message')->nullable();
                $table->integer('execution_time_ms')->default(0);
                $table->string('client_platform')->nullable();
                $table->string('client_app_version')->nullable();
                $table->json('negotiation')->nullable();
                $table->timestamps();
            });
        }
    }

    private function service(): Workflows
    {
        $service = new Workflows();
        $service->register('CAPTURE', CapturingHandler::class);

        return $service;
    }

    public function test_explicit_scalar_identity_overrides_the_resolver(): void
    {
        // Even though nobody is logged in, the explicit id must be honoured.
        $this->service()->execute('CAPTURE', identity: 77);

        $this->assertSame(77, CapturingHandler::$userId);
    }

    public function test_explicit_user_object_identity_flows_to_context(): void
    {
        $user = (object) ['id' => 501];

        $this->service()->execute('CAPTURE', identity: $user);

        $this->assertSame(501, CapturingHandler::$userId);
        $this->assertSame($user, CapturingHandler::$user);
    }

    public function test_falls_back_to_bound_resolver_when_no_explicit_identity(): void
    {
        // Bind a custom resolver to prove the seam is used (no Laravel guard needed).
        $this->app->bind(WorkflowIdentityResolver::class, function () {
            return new class implements WorkflowIdentityResolver {
                public function resolve(?Request $request = null, ?string $ssoProviderAlias = null): WorkflowIdentity
                {
                    return new WorkflowIdentity(id: 900);
                }
            };
        });

        $this->service()->execute('CAPTURE');

        $this->assertSame(900, CapturingHandler::$userId);
    }

    public function test_workflow_pinned_sso_provider_alias_is_passed_to_the_resolver(): void
    {
        // A resolver that records the per-workflow provider hint it was given.
        $holder = new \stdClass();
        $holder->alias = 'UNSET';

        $this->app->bind(WorkflowIdentityResolver::class, function () use ($holder) {
            return new class($holder) implements WorkflowIdentityResolver {
                public function __construct(private \stdClass $holder) {}

                public function resolve(?Request $request = null, ?string $ssoProviderAlias = null): WorkflowIdentity
                {
                    $this->holder->alias = $ssoProviderAlias;
                    return new WorkflowIdentity(id: 5);
                }
            };
        });

        Workflow::create([
            'site_id' => 1,
            'name' => 'Pinned',
            'alias' => 'PINNED_WF',
            'auth_required' => false,
            'sso_provider_alias' => 'my-provider',
            'handler' => CapturingHandler::class,
            'publish_status' => true,
            'config' => null,
        ]);

        $this->service()->execute('PINNED_WF');

        $this->assertSame('my-provider', $holder->alias);
    }

    public function test_workflow_without_a_pin_passes_null_to_the_resolver(): void
    {
        $holder = new \stdClass();
        $holder->alias = 'UNSET';

        $this->app->bind(WorkflowIdentityResolver::class, function () use ($holder) {
            return new class($holder) implements WorkflowIdentityResolver {
                public function __construct(private \stdClass $holder) {}

                public function resolve(?Request $request = null, ?string $ssoProviderAlias = null): WorkflowIdentity
                {
                    $this->holder->alias = $ssoProviderAlias;
                    return new WorkflowIdentity(id: 5);
                }
            };
        });

        // 'CAPTURE' has no workflow row (runs via the registered handler), so
        // $workflow->sso_provider_alias is null.
        $this->service()->execute('CAPTURE');

        $this->assertNull($holder->alias);
    }

    public function test_default_binding_is_the_auth_resolver_and_yields_anonymous_when_logged_out(): void
    {
        $this->assertInstanceOf(
            AuthIdentityResolver::class,
            $this->app->make(WorkflowIdentityResolver::class)
        );

        $this->service()->execute('CAPTURE');

        // No authenticated guard in the test env -> anonymous -> null user id.
        $this->assertNull(CapturingHandler::$userId);
    }

    public function test_external_identity_and_claims_flow_into_the_context(): void
    {
        $this->app->bind(WorkflowIdentityResolver::class, function () {
            return new class implements WorkflowIdentityResolver {
                public function resolve(?Request $request = null, ?string $ssoProviderAlias = null): WorkflowIdentity
                {
                    return new WorkflowIdentity(
                        id: 'auth0|xyz-1',
                        claims: ['roles' => ['buyer'], 'tenant' => 'acme'],
                        provider: 'xyzsite-sso',
                    );
                }
            };
        });

        $this->service()->execute('CAPTURE');

        // External subject: no local id, but reachable via getExternalUserId().
        $this->assertNull(CapturingHandler::$userId);
        $this->assertSame('auth0|xyz-1', CapturingHandler::$externalUserId);
        $this->assertSame(['buyer'], CapturingHandler::$claims['roles']);
        $this->assertSame('xyzsite-sso', CapturingHandler::$identity->provider);
    }

    public function test_external_identity_is_persisted_to_workflow_logs(): void
    {
        $this->app->bind(WorkflowIdentityResolver::class, function () {
            return new class implements WorkflowIdentityResolver {
                public function resolve(?Request $request = null, ?string $ssoProviderAlias = null): WorkflowIdentity
                {
                    return new WorkflowIdentity(id: 'auth0|xyz-1', provider: 'xyzsite-sso');
                }
            };
        });

        $this->service()->execute('CAPTURE');

        $row = DB::table('workflow_logs')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->user_id);                       // external subject -> no local id
        $this->assertSame('auth0|xyz-1', $row->external_user_id);
        $this->assertSame('xyzsite-sso', $row->sso_provider_alias);
    }

    public function test_local_identity_logs_user_id_and_no_external(): void
    {
        $this->service()->execute('CAPTURE', identity: 501);

        $row = DB::table('workflow_logs')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame(501, (int) $row->user_id);
        $this->assertNull($row->external_user_id);
        $this->assertNull($row->sso_provider_alias);
    }

    // --- auth_required enforcement -------------------------------------------

    private function secureWorkflow(): void
    {
        Workflow::create([
            'name' => 'Secure',
            'alias' => 'SECURE',
            'site_id' => 1,
            'auth_required' => true,
            'handler' => CapturingHandler::class,
            'publish_status' => true,
        ]);
    }

    public function test_auth_required_workflow_blocks_anonymous_callers(): void
    {
        $this->secureWorkflow();

        $response = $this->service()->execute('SECURE');

        $this->assertFalse($response->getSuccess());
        $this->assertSame(401, $response->getStatusCode());
        $this->assertNull(CapturingHandler::$identity, 'handler must not run when blocked');

        // The blocked attempt is still audited.
        $row = DB::table('workflow_logs')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->is_success);
    }

    public function test_auth_required_workflow_runs_for_an_authenticated_caller(): void
    {
        $this->secureWorkflow();

        $response = $this->service()->execute('SECURE', identity: 501);

        $this->assertTrue($response->getSuccess());
        $this->assertNull($response->getStatusCode());
        $this->assertSame(501, CapturingHandler::$userId);
    }

    public function test_identity_raw_and_claims_interpolate_in_a_declarative_workflow(): void
    {
        $this->app->bind(WorkflowIdentityResolver::class, function () {
            return new class implements WorkflowIdentityResolver {
                public function resolve(?Request $request = null, ?string $ssoProviderAlias = null): WorkflowIdentity
                {
                    return new WorkflowIdentity(
                        id: 'auth0|9',
                        claims: ['email' => 'curated@x.io'],
                        provider: 'p',
                        raw: ['user' => ['email' => 'raw@x.io', 'tier' => 'gold']],
                    );
                }
            };
        });

        Workflow::create([
            'name' => 'Raw',
            'alias' => 'WF_RAW',
            'site_id' => 1,
            'auth_required' => false,
            'publish_status' => true,
            'config' => [
                'version' => '1.0',
                'target' => ['type' => 'none'],
                'on_success' => [
                    'data' => [
                        'rawEmail'     => '{{ identity.raw.user.email }}',
                        'rawTier'      => '{{ identity.raw.user.tier }}',
                        'claimEmail'   => '{{ claims.email }}',
                        'provider'     => '{{ identity.provider }}',
                    ],
                ],
            ],
        ]);

        $data = $this->service()->execute('WF_RAW')->toArray()['data'];

        $this->assertSame('raw@x.io', $data['rawEmail']);   // {{ identity.raw.* }}
        $this->assertSame('gold', $data['rawTier']);
        $this->assertSame('curated@x.io', $data['claimEmail']); // {{ claims.* }} still separate
        $this->assertSame('p', $data['provider']);
    }

    public function test_rejected_credential_is_401_even_without_auth_required(): void
    {
        // CAPTURE is not auth_required, but a rejected credential (reject policy)
        // must still be blocked.
        $this->app->bind(WorkflowIdentityResolver::class, function () {
            return new class implements WorkflowIdentityResolver {
                public function resolve(?Request $request = null, ?string $ssoProviderAlias = null): WorkflowIdentity
                {
                    return WorkflowIdentity::rejected('xyzsite-sso');
                }
            };
        });

        $response = $this->service()->execute('CAPTURE');

        $this->assertFalse($response->getSuccess());
        $this->assertSame(401, $response->getStatusCode());
        $this->assertNull(CapturingHandler::$identity, 'handler must not run when blocked');
    }
}
