<?php

namespace HashtagCms\Workflows\Tests\Unit;

use HashtagCms\Workflows\Engine\WorkflowIdentity;
use HashtagCms\Workflows\Tests\TestCase;

class WorkflowIdentityTest extends TestCase
{
    public function test_anonymous_is_not_authenticated_and_not_failed(): void
    {
        $identity = WorkflowIdentity::anonymous();

        $this->assertTrue($identity->isAnonymous());
        $this->assertFalse($identity->isAuthenticated());
        $this->assertFalse($identity->failed);
        $this->assertNull($identity->localUserId());
        $this->assertNull($identity->externalUserId());
    }

    public function test_rejected_is_failed_and_not_anonymous(): void
    {
        $identity = WorkflowIdentity::rejected('xyzsite-sso');

        $this->assertTrue($identity->failed);
        $this->assertFalse($identity->isAnonymous());
        $this->assertFalse($identity->isAuthenticated());
        $this->assertSame('xyzsite-sso', $identity->provider);
    }

    public function test_integer_id_routes_to_local_user_id(): void
    {
        $identity = new WorkflowIdentity(id: 42);

        $this->assertTrue($identity->isAuthenticated());
        $this->assertSame(42, $identity->localUserId());
        $this->assertNull($identity->externalUserId());
    }

    public function test_string_id_routes_to_external_user_id(): void
    {
        $identity = new WorkflowIdentity(id: 'auth0|abc-123');

        $this->assertTrue($identity->isAuthenticated());
        $this->assertNull($identity->localUserId());
        $this->assertSame('auth0|abc-123', $identity->externalUserId());
    }

    public function test_from_normalizes_scalar_object_and_self(): void
    {
        $this->assertSame(7, WorkflowIdentity::from(7)->localUserId());
        $this->assertSame('u-9', WorkflowIdentity::from('u-9')->externalUserId());

        $user = (object) ['id' => 99, 'email' => 'a@b.co'];
        $fromUser = WorkflowIdentity::from($user);
        $this->assertSame(99, $fromUser->localUserId());
        $this->assertSame($user, $fromUser->user);

        $existing = WorkflowIdentity::anonymous();
        $this->assertSame($existing, WorkflowIdentity::from($existing));

        $this->assertTrue(WorkflowIdentity::from(null)->isAnonymous());
    }

    public function test_from_user_derives_id_and_exposes_claims(): void
    {
        $user = (object) ['id' => 5];
        $identity = WorkflowIdentity::fromUser($user, ['roles' => ['admin']]);

        $this->assertSame(5, $identity->localUserId());
        $this->assertSame(['admin'], $identity->claim('roles'));
        $this->assertSame('fallback', $identity->claim('missing', 'fallback'));
    }
}
