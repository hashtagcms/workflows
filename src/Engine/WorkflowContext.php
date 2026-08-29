<?php

namespace HashtagCms\Workflows\Engine;

use HashtagCms\Workflows\Models\Workflow;
use Illuminate\Http\Request;

class WorkflowContext
{
    public function __construct(
        public readonly Workflow $workflow,
        public readonly array $payload = [],
        public readonly int $siteId = 1,
        public readonly ?int $userId = null,
        public readonly ?string $platform = 'android',
        public readonly ?Request $request = null,
        public readonly mixed $user = null,
        public readonly ?string $appVersion = null,
        public readonly array $capabilities = []
    ) {}

    public function getWorkflow(): Workflow
    {
        return $this->workflow;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getSiteId(): int
    {
        return $this->siteId;
    }

    public function getUserId(): ?int
    {
        return $this->userId ?? ($this->user ? $this->user->id : null);
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function getAppVersion(): ?string
    {
        return $this->appVersion;
    }

    /**
     * @return array<int, string>
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    public function getUser(): mixed
    {
        return $this->user;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }
}
