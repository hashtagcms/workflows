<?php

namespace HashtagCms\Workflows\Contracts;

use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowResponse;

interface WorkflowHandlerInterface
{
    /**
     * Executes the workflow pipeline.
     */
    public function handle(WorkflowContext $context): WorkflowResponse;
}
