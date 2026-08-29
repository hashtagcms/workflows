<?php

namespace HashtagCms\Workflows\Database\Seeders;

use Illuminate\Database\Seeder;
use HashtagCms\Workflows\Models\Workflow;

/**
 * Example workflow: fetch a *paginated* list of photos from a public REST API
 * (https://picsum.photos/v2/list?page=2&limit=4).
 *
 * Demonstrates:
 *  - optional validated input (`rules` with Laravel's `sometimes`)
 *  - forwarding request payload into HTTP `query` params with `{{ ... }}`
 *  - `default:` filters so the workflow works even with no input
 *  - interpolating request values into directive messages
 *
 * Example request payload: { "page": 2, "limit": 4 }
 */
class LoadPhotosPaginatedWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        Workflow::updateOrCreate(
            ['alias' => 'WORKFLOW_LOAD_PHOTOS_PAGED'],
            [
                'site_id'        => 1,
                'name'           => 'Load Photos (Paginated)',
                'description'    => 'Fetches a page of photos from https://picsum.photos/v2/list using page & limit query params.',
                'auth_required'  => false,
                'handler'        => null,
                'publish_status' => true,
                'config'         => [
                    'version' => '1.0',
                    'rules'   => [
                        'page'  => 'sometimes|integer|min:1',
                        'limit' => 'sometimes|integer|min:1|max:100',
                    ],
                    'target' => [
                        'type'   => 'http',
                        'method' => 'GET',
                        'url'    => 'https://picsum.photos/v2/list',
                        'query'  => [
                            'page'  => '{{ payload.page | default: 1 }}',
                            'limit' => '{{ payload.limit | default: 4 }}',
                        ],
                        'headers' => ['Accept' => 'application/json'],
                        'timeout' => 15,
                    ],
                    'on_success' => [
                        'directives' => [
                            [
                                'type'   => 'render_photos',
                                'action' => 'render',
                                'page'   => '{{ payload.page | default: 1 }}',
                                'limit'  => '{{ payload.limit | default: 4 }}',
                                'items'  => '{{ response.body }}',
                            ],
                            [
                                'type'    => 'toast',
                                'level'   => 'success',
                                'message' => 'Loaded page {{ payload.page | default: 1 }} ({{ payload.limit | default: 4 }} photos).',
                            ],
                        ],
                    ],
                    'on_failure' => [
                        'directives' => [
                            [
                                'type'    => 'toast',
                                'level'   => 'error',
                                'message' => 'Could not load photos for that page.',
                            ],
                        ],
                    ],
                ],
            ]
        );
    }
}
