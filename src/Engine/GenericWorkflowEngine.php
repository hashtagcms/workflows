<?php

namespace HashtagCms\Workflows\Engine;

use Illuminate\Support\Facades\Validator;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowResponse;
use HashtagCms\Workflows\Engine\VariableInterpolator;
use HashtagCms\Workflows\Engine\TargetAdapters\TargetAdapterInterface;
use HashtagCms\Workflows\Engine\TargetAdapters\HttpTargetAdapter;
use HashtagCms\Workflows\Engine\TargetAdapters\ServiceTargetAdapter;
use HashtagCms\Workflows\Engine\TargetAdapters\EventTargetAdapter;
use HashtagCms\Workflows\Engine\TargetAdapters\CustomClassAdapter;

class GenericWorkflowEngine
{
    /**
     * Map of target types to adapter classes.
     */
    protected array $adapters = [
        'http' => HttpTargetAdapter::class,
        'http_request' => HttpTargetAdapter::class,
        'service' => ServiceTargetAdapter::class,
        'service_call' => ServiceTargetAdapter::class,
        'event' => EventTargetAdapter::class,
        'event_dispatch' => EventTargetAdapter::class,
        'custom_class' => CustomClassAdapter::class,
        'handler' => CustomClassAdapter::class,
    ];

    /**
     * Execute a workflow declaratively via its configuration.
     *
     * @param WorkflowContext $context
     * @return WorkflowResponse
     */
    public function execute(WorkflowContext $context): WorkflowResponse
    {
        $workflow = $context->getWorkflow();
        $config = $workflow->config ?? [];
        $payload = $context->getPayload();

        // 1. Validation Step
        $rules = $config['validation']['rules'] ?? ($config['rules'] ?? []);
        if (!empty($rules)) {
            $messages = $config['validation']['messages'] ?? ($config['messages'] ?? []);
            $validator = Validator::make($payload, $rules, $messages);

            if ($validator->fails()) {
                $errorMsg = $validator->errors()->first();
                $response = (new WorkflowResponse())
                    ->setSuccess(false)
                    ->setMessage($errorMsg);

                $errorDirectivesConfig = $config['validation']['on_error']['directives'] ?? ($config['on_error']['directives'] ?? null);
                if (!empty($errorDirectivesConfig)) {
                    $errorDirectives = VariableInterpolator::interpolate(
                        $errorDirectivesConfig,
                        ['payload' => $payload, 'errors' => $validator->errors()->toArray()]
                    );
                    foreach ($errorDirectives as $dir) {
                        $response->addDirective($dir);
                    }
                } else {
                    $response->addToast($errorMsg, 'error');
                }

                return $response;
            }
        }

        // 2. Build Context Dictionary for Interpolation
        $user = $context->getUser();
        $interpolationContext = [
            'payload' => $payload,
            'site' => ['id' => $context->getSiteId()],
            'platform' => $context->getPlatform(),
            'user' => $user ? (method_exists($user, 'toArray') ? $user->toArray() : (array)$user) : [],
            'claims' => $context->getClaims(),
            'identity' => $this->identityContext($context),
            'config' => $config,
            'workflow' => [
                'id' => $workflow->id ?? 0,
                'alias' => $workflow->alias ?? '',
                'name' => $workflow->name ?? ''
            ],
            'workflow_context' => $context
        ];

        // 3. Target Execution Step
        $targetConfig = $config['target'] ?? null;
        $targetResult = [
            'success' => true,
            'status' => 200,
            'body' => null,
            'headers' => [],
            'error' => null
        ];

        if (!empty($targetConfig)) {
            $targetType = strtolower($targetConfig['type'] ?? 'http_request');

            if ($targetType !== 'none' && $targetType !== 'direct') {
                $adapterClass = $this->adapters[$targetType] ?? null;

                if (!$adapterClass || !class_exists($adapterClass)) {
                    throw new \InvalidArgumentException("Unsupported workflow target type '{$targetType}'.");
                }

                /** @var TargetAdapterInterface $adapter */
                $adapter = app()->make($adapterClass);
                $targetResult = $adapter->execute($targetConfig, $interpolationContext);

                // If target adapter already compiled a WorkflowResponse (e.g. CustomClassAdapter)
                if (!empty($targetResult['is_workflow_response']) && $targetResult['workflow_response'] instanceof WorkflowResponse) {
                    return $targetResult['workflow_response'];
                }
            }
        }

        // 4. Update Interpolation Context with Target Result
        $interpolationContext['response'] = [
            'success' => $targetResult['success'],
            'status' => $targetResult['status'],
            'body' => $targetResult['body'],
            'headers' => $targetResult['headers']
        ];
        $interpolationContext['error'] = [
            'message' => $targetResult['error']
        ];

        // 5. Evaluate Success vs Failure and Compile Directives
        $isSuccess = $targetResult['success'] && $targetResult['status'] < 400;

        if ($isSuccess) {
            $successConfig = $config['on_success'] ?? [];
            $rawDirectives = $successConfig['directives'] ?? ($config['directives'] ?? []);
            $rawMessage = $successConfig['message'] ?? ($config['message'] ?? 'Workflow executed successfully.');

            $interpolatedDirectives = VariableInterpolator::interpolate($rawDirectives, $interpolationContext);
            $interpolatedMessage = VariableInterpolator::interpolate($rawMessage, $interpolationContext);

            $response = (new WorkflowResponse())
                ->setSuccess(true)
                ->setMessage((string)$interpolatedMessage);

            if (is_array($interpolatedDirectives)) {
                foreach ($interpolatedDirectives as $dir) {
                    if (is_array($dir)) {
                        $response->addDirective($dir);
                    }
                }
            }

            // Optional `data` payload returned in the response's `data` field.
            // Lets a declarative workflow surface the target response (or any
            // interpolated values) — e.g. "data": { "photos": "{{ response.body }}" }.
            $rawData = $successConfig['data'] ?? ($config['data'] ?? null);
            if ($rawData !== null) {
                $interpolatedData = VariableInterpolator::interpolate($rawData, $interpolationContext);
                if (is_array($interpolatedData)) {
                    $response->withData($interpolatedData);
                }
            }

            return $response;
        }

        // Failure Branch
        $failureConfig = $config['on_failure'] ?? [];
        $rawFailDirectives = $failureConfig['directives'] ?? [];
        $rawFailMessage = $failureConfig['message'] ?? ($targetResult['error'] ?? 'Workflow execution failed.');

        $interpolatedFailDirectives = VariableInterpolator::interpolate($rawFailDirectives, $interpolationContext);
        $interpolatedFailMessage = VariableInterpolator::interpolate($rawFailMessage, $interpolationContext);

        $response = (new WorkflowResponse())
            ->setSuccess(false)
            ->setMessage((string)$interpolatedFailMessage);

        if (!empty($interpolatedFailDirectives) && is_array($interpolatedFailDirectives)) {
            foreach ($interpolatedFailDirectives as $dir) {
                if (is_array($dir)) {
                    $response->addDirective($dir);
                }
            }
        } else {
            $response->addToast((string)$interpolatedFailMessage, 'error');
        }

        $rawFailData = $failureConfig['data'] ?? null;
        if ($rawFailData !== null) {
            $interpolatedFailData = VariableInterpolator::interpolate($rawFailData, $interpolationContext);
            if (is_array($interpolatedFailData)) {
                $response->withData($interpolatedFailData);
            }
        }

        return $response;
    }

    /**
     * The `{{ identity.* }}` interpolation namespace: the resolved caller's
     * canonical fields plus the opt-in raw passthrough. Curated attributes stay
     * under `{{ claims.* }}`; `{{ identity.raw.* }}` is the provider's raw
     * validator response, populated only when the provider maps `identity.raw`.
     */
    private function identityContext(WorkflowContext $context): array
    {
        $identity = $context->getIdentity();

        if ($identity === null) {
            return ['user_id' => $context->getUserId(), 'external_user_id' => null, 'provider' => null, 'raw' => []];
        }

        return [
            'user_id' => $identity->id,
            'external_user_id' => $identity->externalUserId(),
            'provider' => $identity->provider,
            'raw' => $identity->raw,
        ];
    }
}
