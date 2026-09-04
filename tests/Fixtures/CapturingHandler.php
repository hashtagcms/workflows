<?php

namespace HashtagCms\Workflows\Tests\Fixtures;

use HashtagCms\Workflows\Contracts\WorkflowHandlerInterface;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowResponse;

/**
 * Records the identity-related fields of the context it receives, so tests can
 * assert how Workflows::execute() resolved the caller.
 */
class CapturingHandler implements WorkflowHandlerInterface
{
    public static ?int $userId = null;
    public static mixed $user = null;
    public static ?string $externalUserId = null;
    public static array $claims = [];
    public static mixed $identity = null;

    public function handle(WorkflowContext $context): WorkflowResponse
    {
        self::$userId = $context->getUserId();
        self::$user = $context->getUser();
        self::$externalUserId = $context->getExternalUserId();
        self::$claims = $context->getClaims();
        self::$identity = $context->getIdentity();

        return WorkflowResponse::make()->setSuccess(true, 'captured');
    }
}
