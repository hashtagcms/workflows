<?php

namespace HashtagCms\Workflows\Tests\Fixtures;

use HashtagCms\Workflows\Engine\WorkflowResponse;

/**
 * A stand-in for the Workflows service, bound in place of the real singleton
 * so controller behaviour can be tested without a database or the CMS core.
 * Method signatures mirror the real service so the facade's named-argument
 * forwarding resolves correctly.
 */
class FakeWorkflows
{
    public ?\Throwable $throw = null;
    public ?WorkflowResponse $response = null;
    public array $registered = ['WORKFLOW_ADD_TO_CART' => 'Handler'];

    /** Captures the arguments of the most recent execute() call for assertions. */
    public array $lastCall = [];

    public function execute(
        string $alias,
        array $payload = [],
        int $siteId = 1,
        ?string $platform = 'android',
        ?string $appVersion = null,
        array $capabilities = []
    ): WorkflowResponse {
        $this->lastCall = compact('alias', 'payload', 'siteId', 'platform', 'appVersion', 'capabilities');

        if ($this->throw !== null) {
            throw $this->throw;
        }

        return $this->response ?? WorkflowResponse::make()->setSuccess(true, 'ok')->toast('done');
    }

    public function getRegistered(): array
    {
        return $this->registered;
    }
}
