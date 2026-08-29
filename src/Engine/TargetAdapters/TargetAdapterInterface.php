<?php

namespace HashtagCms\Workflows\Engine\TargetAdapters;

interface TargetAdapterInterface
{
    /**
     * Execute the target action with interpolated configuration and context.
     *
     * @param array $targetConfig
     * @param array $context
     * @return array Standardized result: ['success' => bool, 'status' => int, 'body' => mixed, 'headers' => array, 'error' => ?string]
     */
    public function execute(array $targetConfig, array $context): array;
}
