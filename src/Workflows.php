<?php

namespace HashtagCms\Workflows;

use HashtagCms\Workflows\Models\Workflow;
use HashtagCms\Workflows\Models\WorkflowLog;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowResponse;
use HashtagCms\Workflows\Engine\WorkflowIdentity;
use HashtagCms\Workflows\Engine\GenericWorkflowEngine;
use HashtagCms\Workflows\Engine\DirectiveNegotiator;
use HashtagCms\Workflows\Contracts\WorkflowHandlerInterface;
use HashtagCms\Workflows\Contracts\WorkflowIdentityResolver;

class Workflows
{
    /**
     * PHP handler aliases registered at runtime. The package ships no built-in
     * handlers — applications register their own (typically in a service
     * provider), or publish the bundled examples with
     * `php artisan hashtagcms-workflows:publish-examples` and register those.
     * Workflows can also be defined declaratively as `workflows` table rows.
     *
     * @var array<string, class-string>
     */
    protected array $registry = [];

    public function register(string $alias, string $handlerClass): self
    {
        $this->registry[$alias] = $handlerClass;
        return $this;
    }

    public function getRegistered(): array
    {
        return $this->registry;
    }

    public function execute(
        string $alias,
        array $payload = [],
        int $siteId = 1,
        ?string $platform = 'android',
        ?string $appVersion = null,
        array $capabilities = [],
        mixed $identity = null
    ): WorkflowResponse {
        $startTime = microtime(true);
        $masterSiteId = (int) config('hashtagcms-workflows.master_site_id', 1);

        $workflow = Workflow::where('alias', $alias)
            ->where('publish_status', true)
            ->where(function($query) use ($siteId, $masterSiteId) {
                $query->where('site_id', $siteId)
                      ->orWhere('site_id', $masterSiteId);
            })
            ->orderByRaw("CASE WHEN site_id = ? THEN 0 ELSE 1 END", [$siteId])
            ->first();

        if (!$workflow) {
            $workflow = new Workflow([
                'name' => $alias,
                'alias' => $alias,
                'site_id' => $siteId,
                'handler' => $this->registry[$alias] ?? null
            ]);
        }

        // Resolve who is executing this workflow. An explicit $identity (passed
        // by a caller that already knows the user) wins; otherwise the bound
        // WorkflowIdentityResolver decides — by default the local Laravel guard.
        // Either way the engine no longer assumes Laravel login is in use.
        $resolvedIdentity = $identity !== null
            ? WorkflowIdentity::from($identity)
            : app(WorkflowIdentityResolver::class)->resolve(request(), $workflow->sso_provider_alias ?? null);

        // Enforce authentication before running anything. A rejected credential
        // (a bad token under an on_failure=reject provider) is always a 401; a
        // workflow flagged auth_required needs *some* resolved identity. Anonymous
        // callers of a non-auth_required workflow pass straight through.
        $authError = null;
        if ($resolvedIdentity->failed) {
            $authError = 'Invalid or expired credentials.';
        } elseif (($workflow->auth_required ?? false) && !$resolvedIdentity->isAuthenticated()) {
            $authError = 'Authentication required.';
        }

        if ($authError !== null) {
            $response = WorkflowResponse::unauthorized($authError);
            $executionMs = (int)((microtime(true) - $startTime) * 1000);
            $this->logRun($alias, $siteId, $resolvedIdentity, $payload, $response, $executionMs, $platform, $appVersion, ['downgraded' => [], 'dropped' => []]);
            return $response;
        }

        $context = new WorkflowContext(
            workflow: $workflow,
            payload: $payload,
            siteId: $siteId,
            userId: $resolvedIdentity->localUserId(),
            platform: $platform,
            user: $resolvedIdentity->user,
            appVersion: $appVersion,
            capabilities: $capabilities,
            claims: $resolvedIdentity->claims,
            identity: $resolvedIdentity
        );

        // If workflow has declarative configuration (validation, rules, target, on_success, directives, steps)
        $hasConfig = !empty($workflow->config);
        if ($hasConfig) {
            $configData = is_string($workflow->config) ? json_decode($workflow->config, true) : $workflow->config;
            if (!empty($configData) && (isset($configData['target']) || isset($configData['directives']) || isset($configData['on_success']) || isset($configData['on_failure']) || isset($configData['validation']) || isset($configData['rules']) || isset($configData['steps']))) {
                $workflow->config = $configData;
                $engine = app()->make(GenericWorkflowEngine::class);
                $response = $engine->execute($context);
            } else {
                $hasConfig = false;
            }
        }

        if (!$hasConfig) {
            // Otherwise execute PHP class handler
            $handlerClass = $workflow->handler ?: ($this->registry[$alias] ?? null);

            if (!$handlerClass || !class_exists($handlerClass)) {
                throw new \RuntimeException("Handler for workflow '{$alias}' not found.");
            }

            /** @var WorkflowHandlerInterface $handler */
            $handler = app()->make($handlerClass);
            $response = $handler->handle($context);
        }

        // Capability negotiation: rewrite the directive list so this client only
        // receives directives it can render. Fail-safe — any issue leaves the
        // response untouched. See DirectiveNegotiator + docs/12.
        $negotiation = ['downgraded' => [], 'dropped' => []];
        if (config('hashtagcms-workflows.negotiation.enabled', true)) {
            try {
                $negotiator = app()->make(DirectiveNegotiator::class);
                $result = $negotiator->negotiate(
                    $response->getDirectives(),
                    $siteId,
                    $platform,
                    $appVersion,
                    $capabilities
                );
                $response->setDirectives($result['directives']);
                $negotiation['downgraded'] = $result['downgraded'];
                $negotiation['dropped'] = $result['dropped'];
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $executionMs = (int)((microtime(true) - $startTime) * 1000);

        $this->logRun($alias, $siteId, $resolvedIdentity, $payload, $response, $executionMs, $platform, $appVersion, $negotiation);

        return $response;
    }

    /**
     * Persist an execution (or a blocked one) to workflow_logs. Fail-safe: any
     * logging error is swallowed so it never masks the workflow's own result.
     */
    private function logRun(
        string $alias,
        int $siteId,
        WorkflowIdentity $identity,
        array $payload,
        WorkflowResponse $response,
        int $executionMs,
        ?string $platform,
        ?string $appVersion,
        array $negotiation
    ): void {
        try {
            $result = $response->toArray();
            WorkflowLog::create([
                'workflow_alias' => $alias,
                'site_id' => $siteId,
                'user_id' => $identity->localUserId(),
                'external_user_id' => $identity->externalUserId(),
                'sso_provider_alias' => $identity->provider,
                'payload' => $payload,
                'response_directives' => $result['directives'] ?? [],
                'is_success' => $result['success'] ?? true,
                'error_message' => ($result['success'] ?? true) ? null : ($result['message'] ?? null),
                'execution_time_ms' => $executionMs,
                'client_platform' => $platform,
                'client_app_version' => $appVersion,
                'negotiation' => (!empty($negotiation['downgraded']) || !empty($negotiation['dropped'])) ? $negotiation : null,
            ]);
        } catch (\Throwable $e) {}
    }
}
