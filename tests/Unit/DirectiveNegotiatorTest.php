<?php

namespace HashtagCms\Workflows\Tests\Unit;

use HashtagCms\Workflows\Engine\DirectiveNegotiator;
use HashtagCms\Workflows\Models\WorkflowDirective;
use HashtagCms\Workflows\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DirectiveNegotiatorTest extends TestCase
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

        $this->seedManifest();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('workflow_directives');
        parent::tearDown();
    }

    private function seedManifest(): void
    {
        $rows = [
            ['type' => 'toast',        'label' => 'Toast',    'platforms' => null,                                    'fallback' => null],
            ['type' => 'navigate',     'label' => 'Navigate', 'platforms' => null,                                    'fallback' => null],
            ['type' => 'haptic',       'label' => 'Haptic',   'platforms' => ['android' => '1.0', 'ios' => '1.0'],    'fallback' => null],
            ['type' => 'mutate_cart',  'label' => 'Cart',     'platforms' => ['web' => '1.0', 'android' => '2.0', 'ios' => '2.0'], 'fallback' => 'toast'],
            ['type' => 'open_ar_view', 'label' => 'AR',       'platforms' => ['ios' => '3.2', 'android' => '3.4'],    'fallback' => 'navigate'],
        ];

        foreach ($rows as $row) {
            WorkflowDirective::create(array_merge($row, ['site_id' => 1, 'publish_status' => true]));
        }
    }

    private function negotiator(): DirectiveNegotiator
    {
        return new DirectiveNegotiator();
    }

    public function test_universal_directives_always_pass(): void
    {
        $result = $this->negotiator()->negotiate(
            [['type' => 'toast', 'message' => 'hi'], ['type' => 'navigate', 'target' => '/x']],
            siteId: 1,
            platform: 'web',
            appVersion: '1.0.0'
        );

        $this->assertCount(2, $result['directives']);
        $this->assertSame([], $result['dropped']);
        $this->assertSame([], $result['downgraded']);
    }

    public function test_platform_unsupported_without_fallback_is_dropped(): void
    {
        // haptic has no `web` entry and no fallback → dropped on web.
        $result = $this->negotiator()->negotiate(
            [['type' => 'haptic', 'intensity' => 'success'], ['type' => 'toast', 'message' => 'hi']],
            siteId: 1,
            platform: 'web',
            appVersion: '5.0.0'
        );

        $this->assertSame(['haptic'], $result['dropped']);
        $this->assertCount(1, $result['directives']);
        $this->assertSame('toast', $result['directives'][0]['type']);
    }

    public function test_version_below_minimum_downgrades_to_fallback(): void
    {
        // mutate_cart needs android 2.0; client is 1.5 → fallback toast.
        $result = $this->negotiator()->negotiate(
            [['type' => 'mutate_cart', 'action' => 'add']],
            siteId: 1,
            platform: 'android',
            appVersion: '1.5.0'
        );

        $this->assertSame([['from' => 'mutate_cart', 'to' => 'toast']], $result['downgraded']);
        $this->assertSame([['type' => 'toast']], $result['directives']);
    }

    public function test_version_at_or_above_minimum_is_supported(): void
    {
        $result = $this->negotiator()->negotiate(
            [['type' => 'mutate_cart', 'action' => 'add']],
            siteId: 1,
            platform: 'android',
            appVersion: '2.0.0'
        );

        $this->assertSame([], $result['downgraded']);
        $this->assertSame([], $result['dropped']);
        $this->assertSame('mutate_cart', $result['directives'][0]['type']);
    }

    public function test_fallback_chain_lands_on_first_supported(): void
    {
        // open_ar_view needs android 3.4 → fallback navigate (universal) supported.
        $result = $this->negotiator()->negotiate(
            [['type' => 'open_ar_view', 'modelUrl' => 'x']],
            siteId: 1,
            platform: 'android',
            appVersion: '3.0.0'
        );

        $this->assertSame([['from' => 'open_ar_view', 'to' => 'navigate']], $result['downgraded']);
        $this->assertSame([['type' => 'navigate']], $result['directives']);
    }

    public function test_unknown_type_passes_through(): void
    {
        $result = $this->negotiator()->negotiate(
            [['type' => 'brand_new_directive', 'foo' => 'bar']],
            siteId: 1,
            platform: 'web',
            appVersion: '1.0.0'
        );

        $this->assertSame([['type' => 'brand_new_directive', 'foo' => 'bar']], $result['directives']);
        $this->assertSame([], $result['dropped']);
    }

    public function test_null_platform_assumes_supported(): void
    {
        $result = $this->negotiator()->negotiate(
            [['type' => 'mutate_cart', 'action' => 'add'], ['type' => 'haptic', 'intensity' => 'x']],
            siteId: 1,
            platform: null
        );

        $this->assertCount(2, $result['directives']);
        $this->assertSame([], $result['dropped']);
    }

    public function test_explicit_capabilities_override_version_resolution(): void
    {
        // Only 'toast' is declared renderable → mutate_cart downgrades to toast,
        // navigate (not in caps, no fallback) is dropped.
        $result = $this->negotiator()->negotiate(
            [['type' => 'mutate_cart', 'action' => 'add'], ['type' => 'navigate', 'target' => '/x'], ['type' => 'toast', 'message' => 'hi']],
            siteId: 1,
            platform: 'android',
            appVersion: '9.9.9',
            capabilities: ['toast']
        );

        $this->assertSame([['from' => 'mutate_cart', 'to' => 'toast']], $result['downgraded']);
        $this->assertSame(['navigate'], $result['dropped']);
        $this->assertCount(2, $result['directives']); // downgraded toast + original toast
    }

    public function test_empty_manifest_passes_everything_through(): void
    {
        WorkflowDirective::query()->forceDelete();

        $input = [['type' => 'mutate_cart', 'action' => 'add'], ['type' => 'haptic', 'intensity' => 'x']];
        $result = $this->negotiator()->negotiate($input, siteId: 1, platform: 'web', appVersion: '1.0.0');

        $this->assertSame($input, $result['directives']);
        $this->assertSame([], $result['dropped']);
        $this->assertSame([], $result['downgraded']);
    }

    public function test_catalog_filters_to_client_when_platform_given(): void
    {
        // On web, haptic (android/ios only) is excluded; universal + web ones remain.
        $catalog = $this->negotiator()->catalog(1, 'web', '5.0.0');
        $types = array_column($catalog, 'type');

        $this->assertContains('toast', $types);
        $this->assertContains('mutate_cart', $types);
        $this->assertNotContains('haptic', $types);
        $this->assertNotContains('open_ar_view', $types);
    }
}
