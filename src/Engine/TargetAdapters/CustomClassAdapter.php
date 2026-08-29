<?php

namespace HashtagCms\Workflows\Engine\TargetAdapters;

use HashtagCms\Workflows\Contracts\WorkflowHandlerInterface;

class CustomClassAdapter implements TargetAdapterInterface
{
    public function execute(array $targetConfig, array $context): array
    {
        $class = $targetConfig['class'] ?? ($targetConfig['handler'] ?? null);

        if (!$class || !class_exists($class)) {
            return [
                'success' => false,
                'status' => 500,
                'body' => null,
                'headers' => [],
                'error' => "Custom handler class '{$class}' not found."
            ];
        }

        try {
            /** @var WorkflowHandlerInterface $handler */
            $handler = app()->make($class);
            $workflowContext = $context['workflow_context'] ?? null;

            if ($workflowContext && method_exists($handler, 'handle')) {
                $response = $handler->handle($workflowContext);
                return [
                    'success' => $response->toArray()['success'] ?? true,
                    'status' => 200,
                    'body' => $response->toArray(),
                    'headers' => [],
                    'error' => null,
                    'is_workflow_response' => true,
                    'workflow_response' => $response
                ];
            }

            return [
                'success' => false,
                'status' => 500,
                'body' => null,
                'headers' => [],
                'error' => "Invalid handler structure on class '{$class}'."
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 500,
                'body' => null,
                'headers' => [],
                'error' => 'Custom class execution error: ' . $e->getMessage()
            ];
        }
    }
}
