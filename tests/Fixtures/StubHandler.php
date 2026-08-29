<?php

namespace HashtagCms\Workflows\Tests\Fixtures;

use HashtagCms\Workflows\Contracts\WorkflowHandlerInterface;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowResponse;

/**
 * A minimal custom workflow handler used to exercise the custom_class /
 * handler target adapter path in the engine.
 */
class StubHandler implements WorkflowHandlerInterface
{
    public function handle(WorkflowContext $context): WorkflowResponse
    {
        return WorkflowResponse::make()
            ->setSuccess(true, 'handled by stub')
            ->toast('from stub', 'success');
    }
}
