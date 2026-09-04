<?php

namespace HashtagCms\Workflows\Tests\Unit;

use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowIdentity;
use HashtagCms\Workflows\Models\Workflow;
use HashtagCms\Workflows\Tests\TestCase;

class WorkflowContextIdentityTest extends TestCase
{
    private function context(array $overrides = []): WorkflowContext
    {
        return new WorkflowContext(
            workflow: new Workflow(['alias' => 'T', 'name' => 'T']),
            claims: $overrides['claims'] ?? [],
            identity: $overrides['identity'] ?? null,
            userId: $overrides['userId'] ?? null,
            user: $overrides['user'] ?? null,
        );
    }

    public function test_defaults_are_backward_compatible(): void
    {
        $context = $this->context();

        $this->assertNull($context->getUserId());
        $this->assertNull($context->getExternalUserId());
        $this->assertNull($context->getIdentity());
        $this->assertSame([], $context->getClaims());
    }

    public function test_local_identity_exposes_user_id_but_no_external(): void
    {
        $identity = new WorkflowIdentity(id: 42, claims: ['roles' => ['admin']]);
        $context = $this->context(['identity' => $identity]);

        $this->assertSame(42, $context->getUserId());
        $this->assertNull($context->getExternalUserId());
        $this->assertSame(['admin'], $context->claim('roles'));
        $this->assertSame($identity, $context->getIdentity());
    }

    public function test_external_identity_exposes_external_id_and_null_local_id(): void
    {
        $context = $this->context([
            'identity' => new WorkflowIdentity(id: 'auth0|abc', claims: ['email' => 'a@b.co']),
        ]);

        $this->assertNull($context->getUserId());
        $this->assertSame('auth0|abc', $context->getExternalUserId());
        $this->assertSame('a@b.co', $context->claim('email'));
    }

    public function test_explicit_user_id_still_wins_when_no_identity(): void
    {
        $context = $this->context(['userId' => 7]);

        $this->assertSame(7, $context->getUserId());
        $this->assertNull($context->getExternalUserId());
    }

    public function test_claim_returns_default_when_missing(): void
    {
        $context = $this->context(['claims' => ['tenant' => 'acme']]);

        $this->assertSame('acme', $context->claim('tenant'));
        $this->assertSame('fallback', $context->claim('nope', 'fallback'));
    }
}
