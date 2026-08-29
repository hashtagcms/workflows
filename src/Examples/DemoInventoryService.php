<?php

namespace HashtagCms\Workflows\Examples;

/**
 * A throwaway service used only by the example "service" workflow so newcomers
 * can see how a `service` target maps config -> a PHP method call.
 *
 * The engine's ServiceTargetAdapter resolves this class from the container and
 * calls the configured method with the interpolated `arguments` as named
 * parameters. Whatever the method returns becomes `{{ response.body }}`.
 */
class DemoInventoryService
{
    public function check(string $sku = 'DEMO-1', int $quantity = 1): array
    {
        $available = 5; // pretend this came from a database / warehouse API

        return [
            'sku'       => $sku,
            'requested' => $quantity,
            'available' => $available,
            'in_stock'  => $quantity <= $available,
        ];
    }
}
