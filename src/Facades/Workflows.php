<?php

namespace HashtagCms\Workflows\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \HashtagCms\Workflows\Workflows register(string $alias, string $handlerClass)
 * @method static array getRegistered()
 * @method static \HashtagCms\Workflows\Engine\WorkflowResponse execute(string $alias, array $payload = [], int $siteId = 1, ?string $platform = 'android')
 *
 * @see \HashtagCms\Workflows\Workflows
 */
class Workflows extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'hashtagcms.workflows';
    }
}
