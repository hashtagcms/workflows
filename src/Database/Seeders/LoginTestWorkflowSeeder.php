<?php

namespace HashtagCms\Workflows\Database\Seeders;

use Illuminate\Database\Seeder;
use HashtagCms\Workflows\Models\Workflow;

/**
 * Test workflow: log a user in against an EXTERNAL app's login endpoint.
 *
 * Demonstrates using a workflow to *obtain* a credential from another service
 * (the complement to the SSO module, which *verifies* one). It POSTs the caller's
 * email/password to a HashtagCMS user-login API and returns the issued token +
 * user to the client via `on_success.data` — nothing is stored in the workflow.
 *
 * Credentials are taken from the execution payload at runtime
 * (`{{ payload.email }}` / `{{ payload.password }}`), never hardcoded here.
 *
 * NOT part of the auto-installed examples (it targets a specific host). Seed it
 * on demand:
 *   php artisan db:seed --class="HashtagCms\Workflows\Database\Seeders\LoginTestWorkflowSeeder"
 *
 * Then run it through the workflow execution API:
 *   POST {host}/api/hashtagcms/public/workflows/v1/execute
 *   { "workflow": "WORKFLOW_LOGIN_TEST",
 *     "payload": { "email": "you@example.com", "password": "•••••" } }
 *
 * Point it at your environment via HASHTAGCMS_WORKFLOWS_TEST_LOGIN_URL (the
 * built-in default is a placeholder and will not resolve).
 */
class LoginTestWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $loginUrl = env(
            'HASHTAGCMS_WORKFLOWS_TEST_LOGIN_URL',
            'https://auth.example.com/api/hashtagcms/public/user/v1/login'
        );

        Workflow::updateOrCreate(
            ['alias' => 'WORKFLOW_LOGIN_TEST'],
            [
                'site_id'        => 1,
                'name'           => 'Login (test, external app)',
                'description'    => 'Logs a user in against an external HashtagCMS login API and returns the issued token + user.',
                'auth_required'  => false,
                'handler'        => null,
                'publish_status' => true,
                'config'         => [
                    'version' => '1.0',

                    // Reject empty/invalid input before we ever call the login API.
                    'validation' => [
                        'rules' => [
                            'email'    => 'required|email',
                            'password' => 'required|string',
                        ],
                        'messages' => [
                            'email.required'    => 'Email is required.',
                            'email.email'       => 'Enter a valid email address.',
                            'password.required' => 'Password is required.',
                        ],
                        'on_error' => [
                            'directives' => [
                                [
                                    'type'    => 'toast',
                                    'level'   => 'error',
                                    'message' => 'Please enter your email and password.',
                                ],
                            ],
                        ],
                    ],

                    'target' => [
                        'type'    => 'http',
                        'method'  => 'POST',
                        'url'     => $loginUrl,
                        'headers' => [
                            'Accept'       => 'application/json',
                            'Content-Type' => 'application/json',
                        ],
                        'body' => [
                            'email'    => '{{ payload.email }}',
                            'password' => '{{ payload.password }}',
                        ],
                        'timeout' => 15,
                    ],

                    'on_success' => [
                        'message' => 'Logged in.',
                        // Hand the issued token + user back to the client. The client
                        // stores the token and sends it as `Authorization: Bearer` on
                        // subsequent workflow calls (which an SSO provider can verify).
                        'data' => [
                            'token'      => '{{ response.body.token.access_token }}',
                            'expires_at' => '{{ response.body.token.expires_at }}',
                            'user'       => '{{ response.body.user }}',
                        ],
                        'directives' => [
                            [
                                'type'    => 'toast',
                                'level'   => 'success',
                                'message' => 'Welcome back!',
                            ],
                            [
                                'type'   => 'navigate',
                                'target' => 'home',
                            ],
                        ],
                    ],

                    'on_failure' => [
                        // Surfaces the login API's own message (e.g. "Email or
                        // password is incorrect.") when present.
                        'message'    => '{{ response.body.message | default: "Login failed. Please check your credentials." }}',
                        'directives' => [
                            [
                                'type'    => 'toast',
                                'level'   => 'error',
                                'message' => '{{ response.body.message | default: "Login failed. Please check your credentials." }}',
                            ],
                        ],
                    ],
                ],
            ]
        );
    }
}
