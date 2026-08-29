<?php

namespace HashtagCms\Workflows;

use HashtagCms\Workflows\Models\Workflow;
use HashtagCms\Workflows\Models\WorkflowLog;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowResponse;
use HashtagCms\Workflows\Engine\GenericWorkflowEngine;
use HashtagCms\Workflows\Engine\DirectiveNegotiator;
use HashtagCms\Workflows\Contracts\WorkflowHandlerInterface;

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
        array $capabilities = []
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

        $context = new WorkflowContext(
            workflow: $workflow,
            payload: $payload,
            siteId: $siteId,
            platform: $platform,
            user: auth()->user(),
            appVersion: $appVersion,
            capabilities: $capabilities
        );

        // If workflow has declarative configuration (validation, rules, target, on_success, directives, steps)
        $hasConfig = !empty($workflow->config);
        if ($hasConfig) {
            $configData = is_string($workflow->config) ? json_decode($workflow->config, true) : $workflow->config;
            if (!empty($configData) && (isset($configData['target']) || isset($configData['directives']) || isset($configData['validation']) || isset($configData['rules']) || isset($configData['steps']))) {
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

        try {
            WorkflowLog::create([
                'workflow_alias' => $alias,
                'site_id' => $siteId,
                'user_id' => auth()->id(),
                'payload' => $payload,
                'response_directives' => $response->toArray()['directives'] ?? [],
                'is_success' => $response->toArray()['success'] ?? true,
                'error_message' => $response->toArray()['success'] ? null : ($response->toArray()['message'] ?? null),
                'execution_time_ms' => $executionMs,
                'client_platform' => $platform,
                'client_app_version' => $appVersion,
                'negotiation' => (!empty($negotiation['downgraded']) || !empty($negotiation['dropped'])) ? $negotiation : null,
            ]);
        } catch (\Throwable $e) {}

        return $response;
    }
}
