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
        public readonly array $capabilities = [],
        public readonly array $claims = [],
        public readonly ?WorkflowIdentity $identity = null
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

    /**
     * The LOCAL integer user id (for workflow_logs.user_id / local User lookups).
     * Null for external subjects and anonymous callers — use getExternalUserId()
     * to reach a non-local (SSO/UUID) subject.
     */
    public function getUserId(): ?int
    {
        return $this->userId
            ?? $this->identity?->localUserId()
            ?? ($this->user ? $this->user->id : null);
    }

    /**
     * External subject reference (e.g. an SSO/UUID `sub`) when the caller is not
     * a local user, else null.
     */
    public function getExternalUserId(): ?string
    {
        return $this->identity?->externalUserId();
    }

    /** The full resolved identity, when one was supplied. */
    public function getIdentity(): ?WorkflowIdentity
    {
        return $this->identity;
    }

    /**
     * Normalized identity claims (roles, tenant, email, …). Empty when unknown.
     * The explicit `claims` argument wins; otherwise they come from the resolved
     * identity, so passing just an identity is enough.
     *
     * @return array<string, mixed>
     */
    public function getClaims(): array
    {
        return $this->claims ?: ($this->identity?->claims ?? []);
    }

    public function claim(string $key, mixed $default = null): mixed
    {
        return $this->getClaims()[$key] ?? $default;
    }

    /**
     * Opt-in raw passthrough of the validator's response (from a provider's
     * `identity.raw` mapping), also exposed as {{ identity.raw.* }}. Empty unless
     * a provider populated it. Prefer getClaims() for stable, curated attributes.
     *
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->identity?->raw ?? [];
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
