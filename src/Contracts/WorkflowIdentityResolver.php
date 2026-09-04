<?php

namespace HashtagCms\Workflows\Contracts;

use HashtagCms\Workflows\Engine\WorkflowIdentity;
use Illuminate\Http\Request;

/**
 * Resolves who is executing a workflow.
 *
 * This is the seam that decouples the engine from Laravel's login. The default
 * binding ({@see \HashtagCms\Workflows\Identity\AuthIdentityResolver}) wraps
 * `auth()`, so nothing changes for apps that use the local guard. Apps fronted
 * by an external login service bind a different implementation (e.g. an SSO
 * driver) instead — that container swap is the whole extension point.
 */
interface WorkflowIdentityResolver
{
    /**
     * Resolve the current identity.
     *
     * Implementations MUST NOT throw when no identity is present — return
     * {@see WorkflowIdentity::anonymous()} so workflows can still run
     * unauthenticated. A hard auth failure (e.g. an invalid/expired token under
     * a reject-mode provider) is signalled with {@see WorkflowIdentity::rejected()},
     * never an exception; the caller decides what to do with it.
     *
     * @param Request|null $request The inbound HTTP request, or null for jobs /
     *                              server-to-server callers with no request.
     * @param string|null  $ssoProviderAlias Optional per-workflow hint naming a
     *                              specific SSO provider to use (from a workflow's
     *                              `sso_provider_alias`). Implementations that do
     *                              not use SSO ignore it; the SSO resolver uses it
     *                              to pin a provider, falling back to the site
     *                              default when the alias does not apply.
     */
    public function resolve(?Request $request = null, ?string $ssoProviderAlias = null): WorkflowIdentity;
}
