<?php

namespace HashtagCms\Workflows\Tests\Feature;

use HashtagCms\Workflows\Engine\WorkflowResponse;
use HashtagCms\Workflows\Tests\Fixtures\FakeWorkflows;
use HashtagCms\Workflows\Tests\TestCase;

class WorkflowExecutionControllerTest extends TestCase
{
    private string $base;
    private FakeWorkflows $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $prefix = config('hashtagcmsapi.route_prefix', 'api/hashtagcms');
        $this->base = '/' . trim($prefix, '/') . '/public/workflows/v1';

        $this->fake = new FakeWorkflows();
        $this->app->instance('hashtagcms.workflows', $this->fake);
    }

    public function test_missing_workflow_parameter_returns_400(): void
    {
        $this->postJson($this->base . '/execute', ['payload' => []])
            ->assertStatus(400)
            ->assertJson(['success' => false])
            ->assertJsonFragment(['message' => 'Missing required parameter: workflow']);
    }

    public function test_successful_execution_is_passed_through(): void
    {
        $this->fake->response = WorkflowResponse::make()
            ->setSuccess(true, 'added')
            ->toast('Added to cart');

        $this->postJson($this->base . '/execute', ['workflow' => 'WORKFLOW_ADD_TO_CART', 'payload' => ['id' => 1]])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'added',
            ])
            ->assertJsonFragment(['message' => 'Added to cart']);
    }

    public function test_exception_message_is_masked_when_debug_off(): void
    {
        config(['app.debug' => false, 'hashtagcms-workflows.expose_error_details' => null]);
        $this->fake->throw = new \RuntimeException('SECRET internal detail: db password leak');

        $response = $this->postJson($this->base . '/execute', ['workflow' => 'X'])
            ->assertStatus(500)
            ->assertJson(['success' => false]);

        $body = $response->getContent();
        $this->assertStringNotContainsString('SECRET internal detail', $body);
        $this->assertStringContainsString('Please try again later', $body);
    }

    public function test_exception_message_is_exposed_when_debug_on(): void
    {
        config(['app.debug' => true, 'hashtagcms-workflows.expose_error_details' => null]);
        $this->fake->throw = new \RuntimeException('DETAILED failure for developer');

        $response = $this->postJson($this->base . '/execute', ['workflow' => 'X'])
            ->assertStatus(500);

        $this->assertStringContainsString('DETAILED failure for developer', $response->getContent());
    }

    public function test_exception_message_is_exposed_when_config_forces_it(): void
    {
        config(['app.debug' => false, 'hashtagcms-workflows.expose_error_details' => true]);
        $this->fake->throw = new \RuntimeException('FORCED visible detail');

        $response = $this->postJson($this->base . '/execute', ['workflow' => 'X'])
            ->assertStatus(500);

        $this->assertStringContainsString('FORCED visible detail', $response->getContent());
    }

    public function test_client_object_is_forwarded_for_negotiation(): void
    {
        $this->postJson($this->base . '/execute', [
            'workflow' => 'WORKFLOW_ADD_TO_CART',
            'payload' => ['id' => 1],
            'client' => ['platform' => 'ios', 'app_version' => '2.4.1'],
            'capabilities' => ['toast', 'navigate'],
        ])->assertOk();

        $this->assertSame('ios', $this->fake->lastCall['platform']);
        $this->assertSame('2.4.1', $this->fake->lastCall['appVersion']);
        $this->assertSame(['toast', 'navigate'], $this->fake->lastCall['capabilities']);
    }

    public function test_legacy_top_level_platform_still_forwarded(): void
    {
        $this->postJson($this->base . '/execute', [
            'workflow' => 'WORKFLOW_ADD_TO_CART',
            'platform' => 'android',
        ])->assertOk();

        $this->assertSame('android', $this->fake->lastCall['platform']);
        $this->assertNull($this->fake->lastCall['appVersion']);
    }

    public function test_directives_endpoint_returns_manifest_shape(): void
    {
        // No workflow_directives table in this harness → negotiator degrades to
        // an empty manifest, but the endpoint contract still holds.
        $this->getJson($this->base . '/directives')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'directives']);
    }

    public function test_health_endpoint_reports_registered_workflows(): void
    {
        $this->getJson($this->base . '/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'module' => 'hashtagcms/workflows',
            ])
            ->assertJsonFragment(['registered_workflows' => ['WORKFLOW_ADD_TO_CART']]);
    }
}
