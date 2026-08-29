<?php

namespace HashtagCms\Workflows\Examples;

use HashtagCms\Workflows\Contracts\WorkflowHandlerInterface;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowResponse;

/**
 * A throwaway PHP handler used by the example "handler" workflow.
 *
 * This shows the NON-declarative style: a workflow whose `handler` column points
 * at a class implementing WorkflowHandlerInterface. When such a workflow runs,
 * the engine's declarative config is skipped entirely and this `handle()` method
 * builds the response in plain PHP — useful when logic is too dynamic for JSON.
 */
class DemoGreetingHandler implements WorkflowHandlerInterface
{
    public function handle(WorkflowContext $context): WorkflowResponse
    {
        $name = $context->get('name', 'there');

        return WorkflowResponse::make()
            ->setSuccess(true, 'Handled in PHP.')
            ->toast("Hello, {$name}! (built by a PHP handler)", 'success')
            ->withData([
                'handled_by' => static::class,
                'platform'   => $context->getPlatform(),
                'site_id'    => $context->getSiteId(),
            ]);
    }
}
