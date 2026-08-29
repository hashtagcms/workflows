<?php

namespace HashtagCms\Workflows\Tests\Unit;

use HashtagCms\Workflows\Engine\GenericWorkflowEngine;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Models\Workflow;
use HashtagCms\Workflows\Tests\Fixtures\StubHandler;
use HashtagCms\Workflows\Tests\TestCase;

class GenericWorkflowEngineTest extends TestCase
{
    private function engine(): GenericWorkflowEngine
    {
        return new GenericWorkflowEngine();
    }

    private function context(array $config, array $payload = []): WorkflowContext
    {
        $workflow = new Workflow([
            'alias' => 'TEST_WORKFLOW',
            'name' => 'Test Workflow',
            'config' => $config,
        ]);

        return new WorkflowContext(
            workflow: $workflow,
            payload: $payload,
            siteId: 1,
            platform: 'android',
            user: null,
        );
    }

    public function test_validation_failure_returns_first_error_with_default_toast(): void
    {
        $response = $this->engine()->execute($this->context([
            'rules' => ['item_id' => 'required'],
        ], []))->toArray();

        $this->assertFalse($response['success']);
        $this->assertNotEmpty($response['message']);
        $this->assertSame('toast', $response['directives'][0]['type']);
        $this->assertSame('error', $response['directives'][0]['level']);
    }

    public function test_validation_failure_uses_custom_on_error_directives(): void
    {
        $response = $this->engine()->execute($this->context([
            'validation' => [
                'rules' => ['email' => 'required|email'],
                'on_error' => [
                    'directives' => [
                        ['type' => 'toast', 'message' => 'Bad: {{ payload.email }}', 'level' => 'error'],
                    ],
                ],
            ],
        ], ['email' => 'not-an-email']))->toArray();

        $this->assertFalse($response['success']);
        $this->assertSame('Bad: not-an-email', $response['directives'][0]['message']);
    }

    public function test_direct_target_success_compiles_interpolated_directives(): void
    {
        $response = $this->engine()->execute($this->context([
            'target' => ['type' => 'none'],
            'on_success' => [
                'message' => 'Added {{ payload.name }}',
                'directives' => [
                    ['type' => 'toast', 'message' => 'Added {{ payload.name }} x{{ payload.qty }}', 'level' => 'success'],
                ],
            ],
        ], ['name' => 'Widget', 'qty' => 2]))->toArray();

        $this->assertTrue($response['success']);
        $this->assertSame('Added Widget', $response['message']);
        $this->assertSame('Added Widget x2', $response['directives'][0]['message']);
    }

    public function test_missing_service_class_triggers_failure_branch(): void
    {
        $response = $this->engine()->execute($this->context([
            'target' => [
                'type' => 'service',
                'service' => ['class' => 'App\\Nonexistent\\Service', 'method' => 'run'],
            ],
            'on_failure' => [
                'directives' => [
                    ['type' => 'toast', 'message' => 'It failed', 'level' => 'error'],
                ],
            ],
        ], []))->toArray();

        $this->assertFalse($response['success']);
        $this->assertSame('It failed', $response['directives'][0]['message']);
    }

    public function test_custom_class_handler_short_circuits_with_its_response(): void
    {
        $response = $this->engine()->execute($this->context([
            'target' => ['type' => 'custom_class', 'class' => StubHandler::class],
            // These directives must be ignored because the handler short-circuits.
            'on_success' => ['directives' => [['type' => 'toast', 'message' => 'should not appear']]],
        ], []))->toArray();

        $this->assertTrue($response['success']);
        $this->assertSame('handled by stub', $response['message']);
        $this->assertSame('from stub', $response['directives'][0]['message']);
    }

    public function test_unsupported_target_type_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->engine()->execute($this->context([
            'target' => ['type' => 'quantum_teleport'],
        ], []));
    }
}
