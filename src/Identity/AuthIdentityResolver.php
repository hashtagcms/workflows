<?php

namespace HashtagCms\Workflows\Identity;

use HashtagCms\Workflows\Contracts\WorkflowIdentityResolver;
use HashtagCms\Workflows\Engine\WorkflowIdentity;
use Illuminate\Http\Request;

/**
 * Default identity resolver: the local Laravel guard.
 *
 * Reproduces the engine's original behaviour — `auth()->user()` / `auth()->id()`
 * — behind the resolver contract. When the guard has no authenticated user (the
 * app doesn't use Laravel login, or the request is unauthenticated) it returns
 * an anonymous identity rather than failing, so workflows still run.
 */
final class AuthIdentityResolver implements WorkflowIdentityResolver
{
    public function resolve(?Request $request = null, ?string $ssoProviderAlias = null): WorkflowIdentity
    {
        // No SSO here — the per-workflow provider hint does not apply to the local guard.
        if (auth()->check()) {
            return WorkflowIdentity::fromUser(auth()->user());
        }

        return WorkflowIdentity::anonymous();
    }
}
