<?php

namespace HashtagCms\Workflows\Engine\TargetAdapters;

use Illuminate\Support\Facades\Event;
use HashtagCms\Workflows\Engine\VariableInterpolator;

class EventTargetAdapter implements TargetAdapterInterface
{
    public function execute(array $targetConfig, array $context): array
    {
        $eventConfig = $targetConfig['event'] ?? $targetConfig;

        $eventClass = $eventConfig['class'] ?? null;
        $rawPayload = $eventConfig['payload'] ?? [];

        if (!$eventClass || !class_exists($eventClass)) {
            return [
                'success' => false,
                'status' => 500,
                'body' => null,
                'headers' => [],
                'error' => "Event class '{$eventClass}' not found."
            ];
        }

        try {
            $payload = VariableInterpolator::interpolate($rawPayload, $context);
            $eventInstance = new $eventClass($payload);
            $responses = Event::dispatch($eventInstance);

            return [
                'success' => true,
                'status' => 200,
                'body' => $responses,
                'headers' => [],
                'error' => null
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 500,
                'body' => null,
                'headers' => [],
                'error' => 'Event dispatch error: ' . $e->getMessage()
            ];
        }
    }
}
