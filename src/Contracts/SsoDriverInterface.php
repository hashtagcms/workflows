<?php

namespace HashtagCms\Workflows\Contracts;

use HashtagCms\Workflows\Engine\WorkflowIdentity;
use HashtagCms\Workflows\Models\WorkflowSsoProvider;
use Illuminate\Http\Request;

/**
 * Verifies a credential against one SSO provider and produces a normalized
 * identity. One implementation per `driver` value on the provider row.
 *
 * Contract (same spirit as WorkflowIdentityResolver): return
 * {@see WorkflowIdentity::anonymous()} when no credential is presented, and
 * {@see WorkflowIdentity::rejected()} when a credential is present but invalid —
 * never throw for those cases. Throwing is reserved for misconfiguration (e.g. a
 * required optional library is missing).
 */
interface SsoDriverInterface
{
    public function resolve(WorkflowSsoProvider $provider, ?Request $request): WorkflowIdentity;
}
