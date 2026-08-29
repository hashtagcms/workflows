<?php

namespace HashtagCms\Workflows\Database\Seeders;

use Illuminate\Database\Seeder;
use HashtagCms\Workflows\Models\Workflow;

/**
 * Example workflow: fetch a list of photos from a public REST API
 * (https://picsum.photos/v2/list) and return the raw list plus a toast.
 *
 * Demonstrates the simplest declarative HTTP workflow: a single GET `target`
 * whose JSON array response is handed straight to a client directive via
 * `{{ response.body }}`.
 */
class LoadPhotosWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        Workflow::updateOrCreate(
            ['alias' => 'WORKFLOW_LOAD_PHOTOS'],
            [
                'site_id'        => 1,
                'name'           => 'Load Photos',
                'description'    => 'Fetches a list of photos from https://picsum.photos/v2/list and returns them to the client.',
                'auth_required'  => false,
                'handler'        => null,
                'publish_status' => true,
                'config'         => [
                    'version' => '1.0',
                    'target'  => [
                        'type'    => 'http',
                        'method'  => 'GET',
                        'url'     => 'https://picsum.photos/v2/list',
                        'headers' => ['Accept' => 'application/json'],
                        'timeout' => 15,
                    ],
                    'on_success' => [
                        'message'    => 'Photos loaded.',
                        'directives' => [
                            [
                                'type'   => 'render_photos',
                                'action' => 'render',
                                'items'  => '{{ response.body }}',
                            ],
                            [
                                'type'    => 'toast',
                                'level'   => 'success',
                                'message' => 'Photos loaded successfully.',
                            ],
                        ],
                    ],
                    'on_failure' => [
                        'directives' => [
                            [
                                'type'    => 'toast',
                                'level'   => 'error',
                                'message' => 'Could not load photos. Please try again.',
                            ],
                        ],
                    ],
                ],
            ]
        );
    }
}
