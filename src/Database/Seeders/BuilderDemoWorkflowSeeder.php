<?php

namespace HashtagCms\Workflows\Database\Seeders;

use Illuminate\Database\Seeder;
use HashtagCms\Workflows\Models\Workflow;

/**
 * A demo workflow that exercises every part of the Interactive Workflow Manager
 * in one config: validation rules, an HTTP target, an interpolated `data`
 * payload built from the target response, and a multi-directive success branch
 * across several categories (toast, mutate_cart, navigate, haptic).
 *
 * Try it: POST /api/hashtagcms/public/workflows/v1/execute
 *   { "workflow": "WORKFLOW_BUILDER_DEMO", "payload": { "code": "SAVE10", "quantity": 1, "page": 1 } }
 *
 * The response `data.photos` comes straight from https://picsum.photos/v2/list.
 */
class BuilderDemoWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        Workflow::updateOrCreate(
            ['alias' => 'WORKFLOW_BUILDER_DEMO'],
            [
                'site_id'        => 1,
                'name'           => 'Builder Demo — Apply Coupon',
                'description'    => 'Demo for the Interactive Workflow Manager: validation + HTTP target + interpolated data + multi-category directives.',
                'auth_required'  => false,
                'handler'        => null,
                'publish_status' => true,
                'config'         => [
                    'version'    => '1.0',
                    'validation' => [
                        'rules' => [
                            'code'     => 'required|string',
                            'quantity' => 'required|integer|min:1',
                        ],
                    ],
                    'target' => [
                        'type'    => 'http',
                        'method'  => 'GET',
                        'url'     => 'https://picsum.photos/v2/list',
                        'query'   => ['page' => '{{ payload.page | default: 1 }}'],
                        'timeout' => 10,
                    ],
                    'on_success' => [
                        'message' => 'Applied {{ payload.code }}',
                        'data'    => [
                            'photos'      => '{{ response.body }}',
                            'appliedCode' => '{{ payload.code }}',
                        ],
                        'directives' => [
                            ['type' => 'toast', 'level' => 'success', 'message' => 'Coupon {{ payload.code }} applied!'],
                            ['type' => 'mutate_cart', 'action' => 'apply_coupon', 'couponCode' => '{{ payload.code }}', 'discountPercent' => 10],
                            ['type' => 'navigate', 'target' => 'cart', 'params' => []],
                            ['type' => 'haptic', 'intensity' => 'success'],
                        ],
                    ],
                    'on_failure' => [
                        'directives' => [
                            ['type' => 'toast', 'level' => 'error', 'message' => 'Could not apply {{ payload.code }}'],
                            ['type' => 'haptic', 'intensity' => 'error'],
                        ],
                    ],
                ],
            ]
        );
    }
}
