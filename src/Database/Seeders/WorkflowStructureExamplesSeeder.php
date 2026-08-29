<?php

namespace HashtagCms\Workflows\Database\Seeders;

use Illuminate\Database\Seeder;
use HashtagCms\Workflows\Models\Workflow;
use HashtagCms\Workflows\Examples\DemoInventoryService;
use HashtagCms\Workflows\Examples\DemoWorkflowEvent;
use HashtagCms\Workflows\Examples\DemoGreetingHandler;

/**
 * A CATALOG of dummy workflows, one per structural pattern the engine supports.
 * Read this file top-to-bottom to learn the whole config shape. Every example is
 * upserted by alias, so this seeder is safe to run repeatedly.
 *
 * Anatomy of a declarative workflow `config`:
 *   version      – free-form label, not interpreted
 *   validation   – { rules, messages, on_error:{ directives } }   (or top-level `rules`)
 *   target       – ONE of: http | service | event | custom_class | none
 *   on_success   – { message, directives, data }  (falls back to top-level `directives`)
 *   on_failure   – { message, directives, data }
 *
 * `data` (optional) is returned in the response's `data` field, interpolated —
 * use it to surface the target response, e.g. "data": { "items": "{{ response.body }}" }.
 *
 * Interpolation tokens usable anywhere in a config value:
 *   {{ payload.* }}         request payload
 *   {{ response.body.* }}   the target's (decoded) response  — only after a target runs
 *   {{ response.status }}   HTTP status of the target
 *   {{ user.* }}            authenticated user (array)
 *   {{ site.id }} {{ platform }}
 *   {{ errors.* }}          validation errors (inside validation.on_error only)
 *   {{ env.KEY }}           environment variable
 *   {{ x | default: 'y' }}  fallback when x is null/missing
 *
 * Directives are FLAT: `{ "type": "...", <fields> }` — the fields sit next to
 *   `type` (e.g. `{ "type": "toast", "message": "...", "level": "success" }`).
 *   Common client types: toast, navigate, open_sheet, haptic, mutate_cart — plus
 *   any custom type your client understands (e.g. show_welcome, render_photos).
 */
class WorkflowStructureExamplesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->examples() as $example) {
            Workflow::updateOrCreate(['alias' => $example['alias']], array_merge([
                'site_id'        => 1,
                'auth_required'  => false,
                'handler'        => null,
                'publish_status' => true,
            ], $example));
        }
    }

    private function examples(): array
    {
        return [

            // 1) DIRECT DIRECTIVES — no target at all. The engine skips straight to
            //    on_success and returns client directives. The simplest workflow shape.
            [
                'alias'       => 'WORKFLOW_EXAMPLE_DIRECT',
                'name'        => 'Example: Direct Directives',
                'description' => 'No external call — just returns a set of client directives (toast, navigate, open_sheet, haptic).',
                'config'      => [
                    'version'    => '1.0',
                    'target'     => ['type' => 'none'], // no external call; skip straight to on_success
                    'on_success' => [
                        'message'    => "Welcome, {{ payload.name | default: 'guest' }}!",
                        'directives' => [
                            ['type' => 'toast',      'level' => 'success', 'message' => "Welcome, {{ payload.name | default: 'guest' }}!"],
                            ['type' => 'navigate',   'target' => '/dashboard', 'params' => ['ref' => "{{ payload.name | default: 'guest' }}"]],
                            ['type' => 'open_sheet', 'sheetId' => 'getting_started', 'title' => 'Getting started'],
                            ['type' => 'haptic',     'intensity' => 'success'],
                        ],
                    ],
                ],
            ],

            // 2) VALIDATION — reject bad input with friendly, interpolated error
            //    directives; on pass, fall through to on_success.
            [
                'alias'       => 'WORKFLOW_EXAMPLE_VALIDATION',
                'name'        => 'Example: Validation',
                'description' => 'Validates the payload; shows a custom error toast on failure, a success toast on pass.',
                'config'      => [
                    'version'    => '1.0',
                    'validation' => [
                        'rules'    => ['email' => 'required|email', 'age' => 'required|integer|min:18'],
                        'messages' => ['email.required' => 'We need your email address.', 'age.min' => 'You must be at least 18.'],
                        'on_error' => [
                            'directives' => [
                                ['type' => 'toast', 'level' => 'error', 'message' => "{{ errors.email.0 | default: 'Please check your input.' }}"],
                            ],
                        ],
                    ],
                    'on_success' => [
                        'directives' => [
                            ['type' => 'toast', 'level' => 'success', 'message' => 'Thanks, {{ payload.email }} — you are verified.'],
                        ],
                    ],
                ],
            ],

            // 3) HTTP POST — send a body + bearer auth to a REST API, then use the
            //    JSON response. Demonstrates on_failure as well.
            [
                'alias'       => 'WORKFLOW_EXAMPLE_HTTP_POST',
                'name'        => 'Example: HTTP POST',
                'description' => 'POSTs a payload to a REST API (jsonplaceholder) with bearer auth and reads the response.',
                'config'      => [
                    'version' => '1.0',
                    'rules'   => ['title' => 'required|string', 'body' => 'required|string'],
                    'target'  => [
                        'type'    => 'http',
                        'method'  => 'POST',
                        'url'     => 'https://jsonplaceholder.typicode.com/posts',
                        'headers' => ['Content-Type' => 'application/json'],
                        'auth'    => ['type' => 'bearer', 'token' => "{{ env.DEMO_API_TOKEN | default: 'demo-token' }}"],
                        'body'    => ['title' => '{{ payload.title }}', 'body' => '{{ payload.body }}', 'userId' => '{{ payload.userId | default: 1 }}'],
                        'timeout' => 15,
                    ],
                    'on_success' => [
                        'directives' => [
                            ['type' => 'toast', 'level' => 'success', 'message' => 'Created post #{{ response.body.id }}.'],
                        ],
                    ],
                    'on_failure' => [
                        'directives' => [
                            ['type' => 'toast', 'level' => 'error', 'message' => 'Could not create the post.'],
                        ],
                    ],
                ],
            ],

            // 4) SERVICE TARGET — call a method on a container-resolved PHP class.
            //    The method's return value becomes {{ response.body }}.
            [
                'alias'       => 'WORKFLOW_EXAMPLE_SERVICE',
                'name'        => 'Example: Service Call',
                'description' => 'Invokes a PHP service method (DemoInventoryService::check) with interpolated arguments.',
                'config'      => [
                    'version' => '1.0',
                    'target'  => [
                        'type'      => 'service',
                        'class'     => DemoInventoryService::class,
                        'method'    => 'check',
                        'arguments' => ['sku' => "{{ payload.sku | default: 'DEMO-1' }}", 'quantity' => '{{ payload.quantity | default: 1 }}'],
                    ],
                    'on_success' => [
                        'directives' => [
                            ['type' => 'toast', 'level' => 'success', 'message' => '{{ response.body.sku }}: {{ response.body.available }} available.'],
                        ],
                    ],
                ],
            ],

            // 5) EVENT TARGET — dispatch a Laravel event. Listener return values (if
            //    any) become {{ response.body }}.
            [
                'alias'       => 'WORKFLOW_EXAMPLE_EVENT',
                'name'        => 'Example: Event Dispatch',
                'description' => 'Dispatches a Laravel event (DemoWorkflowEvent) with an interpolated payload.',
                'config'      => [
                    'version' => '1.0',
                    'target'  => [
                        'type'    => 'event',
                        'class'   => DemoWorkflowEvent::class,
                        'payload' => ['message' => "{{ payload.message | default: 'ping' }}", 'platform' => '{{ platform }}'],
                    ],
                    'on_success' => [
                        'directives' => [
                            ['type' => 'toast', 'level' => 'success', 'message' => 'Event dispatched.'],
                        ],
                    ],
                ],
            ],

            // 6) PHP HANDLER — NO declarative config. Logic lives entirely in a PHP
            //    class (the `handler` column). Use this when logic is too dynamic for
            //    JSON. The engine calls handler->handle($context) and returns its
            //    WorkflowResponse verbatim.
            [
                'alias'       => 'WORKFLOW_EXAMPLE_HANDLER',
                'name'        => 'Example: PHP Handler (no config)',
                'description' => 'A config-less workflow whose logic is a PHP class implementing WorkflowHandlerInterface.',
                'handler'     => DemoGreetingHandler::class,
                'config'      => null,
            ],

        ];
    }
}
