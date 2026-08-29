<?php

namespace HashtagCms\Workflows\Engine\TargetAdapters;

use HashtagCms\Workflows\Engine\VariableInterpolator;

class ServiceTargetAdapter implements TargetAdapterInterface
{
    public function execute(array $targetConfig, array $context): array
    {
        $serviceConfig = $targetConfig['service'] ?? $targetConfig;

        $class = $serviceConfig['class'] ?? null;
        $method = $serviceConfig['method'] ?? 'handle';
        $rawArgs = $serviceConfig['arguments'] ?? [];

        if (!$class || !class_exists($class)) {
            return [
                'success' => false,
                'status' => 500,
                'body' => null,
                'headers' => [],
                'error' => "Target service class '{$class}' not found."
            ];
        }

        try {
            $service = app()->make($class);
            if (!method_exists($service, $method)) {
                return [
                    'success' => false,
                    'status' => 500,
                    'body' => null,
                    'headers' => [],
                    'error' => "Method '{$method}' does not exist on '{$class}'."
                ];
            }

            $arguments = VariableInterpolator::interpolate($rawArgs, $context);
            $result = is_array($arguments) ? app()->call([$service, $method], $arguments) : $service->{$method}($arguments);

            return [
                'success' => true,
                'status' => 200,
                'body' => $result,
                'headers' => [],
                'error' => null
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 500,
                'body' => null,
                'headers' => [],
                'error' => 'Service execution error: ' . $e->getMessage()
            ];
        }
    }
}
