<?php

namespace HashtagCms\Workflows\Tests\Unit;

use HashtagCms\Workflows\Engine\GenericWorkflowEngine;
use HashtagCms\Workflows\Engine\DirectiveNegotiator;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Models\Workflow;
use HashtagCms\Workflows\Models\WorkflowDirective;
use HashtagCms\Workflows\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exercises the computation behind the Interactive Workflow Manager's live
 * preview — a transient (unsaved) config run through the declarative engine plus
 * capability negotiation, exactly as WorkflowBuilderController::preview() does.
 *
 * (The controller's HTTP/save layer extends core's admin CRUD and is covered by
 * the app, not this package-only harness; here we test the engine-level logic.)
 */
class BuilderPreviewTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('workflow_directives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->default(1);
            $table->string('type');
            $table->string('label');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->json('platforms')->nullable();
            $table->json('schema')->nullable();
            $table->string('fallback')->nullable();
            $table->boolean('is_core')->default(false);
            $table->boolean('publish_status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        foreach ([
            ['type' => 'toast',    'label' => 'Toast',    'platforms' => null],
            ['type' => 'navigate', 'label' => 'Navigate', 'platforms' => null],
            ['type' => 'haptic',   'label' => 'Haptic',   'platforms' => ['android' => '1.0', 'ios' => '1.0']],
        ] as $row) {
            WorkflowDirective::create(array_merge($row, ['site_id' => 1, 'publish_status' => true]));
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('workflow_directives');
        parent::tearDown();
    }

    /** Mirror of WorkflowBuilderController::preview() core logic. */
    private function preview(array $config, array $payload = [], ?string $platform = null, ?string $appVersion = null): array
    {
        $workflow = new Workflow([
            'name' => 'Preview',
            'alias' => 'WORKFLOW_PREVIEW',
            'site_id' => 1,
            'config' => $config,
        ]);

        $context = new WorkflowContext(
            workflow: $workflow,
            payload: $payload,
            siteId: 1,
            platform: $platform,
            appVersion: $appVersion
        );

        $response = (new GenericWorkflowEngine())->execute($context);

        $result = (new DirectiveNegotiator())->negotiate($response->getDirectives(), 1, $platform, $appVersion);
        $response->setDirectives($result['directives']);

        return $response->toArray();
    }

    public function test_flat_directives_interpolate_payload(): void
    {
        $out = $this->preview(
            ['directives' => [['type' => 'toast', 'level' => 'success', 'message' => 'Hi {{ payload.name }}']]],
            ['name' => 'Sam'],
            'web',
            '1.0.0'
        );

        $this->assertTrue($out['success']);
        $this->assertSame(
            ['type' => 'toast', 'level' => 'success', 'message' => 'Hi Sam'],
            $out['directives'][0]
        );
    }

    public function test_negotiation_drops_unsupported_directive_on_web(): void
    {
        $out = $this->preview(
            ['directives' => [
                ['type' => 'haptic', 'intensity' => 'success'],
                ['type' => 'toast', 'level' => 'success', 'message' => 'done'],
            ]],
            [],
            'web',
            '5.0.0'
        );

        $types = array_column($out['directives'], 'type');
        $this->assertNotContains('haptic', $types); // native-only, no web support
        $this->assertContains('toast', $types);
    }

    public function test_validation_on_error_directives_are_returned(): void
    {
        $config = [
            'validation' => [
                'rules' => ['email' => 'required|email'],
                'on_error' => [
                    'directives' => [
                        ['type' => 'toast', 'level' => 'error', 'message' => '{{ errors.email.0 }}'],
                    ],
                ],
            ],
        ];

        $out = $this->preview($config, [], 'web', '1.0.0'); // no email -> validation fails

        $this->assertFalse($out['success']);
        $this->assertSame('toast', $out['directives'][0]['type']);
        $this->assertSame('error', $out['directives'][0]['level']);
        $this->assertNotEmpty($out['directives'][0]['message']);
    }

    public function test_declarative_data_is_interpolated_and_returned(): void
    {
        // A declarative workflow can surface data (e.g. a target response) via a
        // `data` key — interpolated like everything else.
        $out = $this->preview(
            ['on_success' => ['data' => ['echo' => '{{ payload.name }}', 'nums' => [1, 2, 3]]]],
            ['name' => 'Sam'],
            'web',
            '1.0.0'
        );

        $this->assertTrue($out['success']);
        $this->assertSame(['echo' => 'Sam', 'nums' => [1, 2, 3]], $out['data']);
    }

    public function test_top_level_rules_are_honoured(): void
    {
        // Rules at the top level (not under `validation`) must still validate.
        $config = [
            'rules' => ['qty' => 'required|integer|min:1'],
            'on_success' => ['directives' => [['type' => 'toast', 'level' => 'success', 'message' => 'ok']]],
        ];

        $fail = $this->preview($config, [], 'web', '1.0.0');
        $this->assertFalse($fail['success']);

        $pass = $this->preview($config, ['qty' => 2], 'web', '1.0.0');
        $this->assertTrue($pass['success']);
    }
}
