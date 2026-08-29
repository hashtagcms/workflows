<?php

namespace HashtagCms\Workflows\Examples;

/**
 * A throwaway event used only by the example "event" workflow so newcomers can
 * see how an `event` target dispatches a Laravel event.
 *
 * The engine's EventTargetAdapter does `new DemoWorkflowEvent($payload)` and
 * dispatches it. Any listener return values become `{{ response.body }}`.
 * With no listeners registered it simply fires and returns an empty array.
 */
class DemoWorkflowEvent
{
    public function __construct(public array $payload = [])
    {
    }
}
