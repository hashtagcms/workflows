<?php

namespace HashtagCms\Workflows\Database\Seeders;

use Illuminate\Database\Seeder;
use HashtagCms\Workflows\Models\Workflow;
use HashtagCms\Workflows\Models\WorkflowSsoProvider;

/**
 * Example SSO provider whose caller presents the token in a CUSTOM HEADER
 * (not the standard `Authorization`), mirroring a real mobile-app request:
 *
 *   authToken: Bearer <opaque/JWE token>
 *   api_key: <key>   storeId: <id>   mobile: <msisdn>   ...
 *
 * It demonstrates three things at once:
 *   1. credential.header  — read the token from `authToken` and strip the
 *      "Bearer " prefix (the resulting bare token becomes {{request.bearer_token}}).
 *   2. header forwarding   — pass the app's extra headers on to the verify
 *      endpoint via {{request.headers.*}} (many APIs need more than the token).
 *   3. identity mapping    — turn the verify response into a WorkflowIdentity
 *      (external user_id + claims), optionally exposing the raw body.
 *
 * The token here is treated as OPAQUE (e.g. an encrypted JWE), so it must be
 * verified by CALLING an introspection / profile ("whoami") endpoint — the `jwt`
 * driver can't verify an encrypted token locally.
 *
 * NOT part of the auto-installed examples (it targets a specific host). Seed on demand:
 *   php artisan db:seed --class="HashtagCms\Workflows\Database\Seeders\SsoCustomHeaderProviderSeeder"
 *
 * Point it at your environment (defaults are non-resolving placeholders):
 *   HASHTAGCMS_WORKFLOWS_SSO_VERIFY_URL=https://api.example.com/user/profile
 *
 * Then exercise it through the execution API with the token in the custom header:
 *   curl -X POST {host}/api/hashtagcms/public/workflows/v1/execute \
 *     -H 'Content-Type: application/json' \
 *     -H 'authToken: Bearer <token>' \
 *     -H 'api_key: <key>' -H 'storeId: <id>' \
 *     --data '{"workflow":"WORKFLOW_WHOAMI_CUSTOM_HEADER"}'
 *
 * Expected:
 *   - no authToken header          -> HTTP 401 "Authentication required."
 *   - present but invalid token    -> HTTP 401 "Invalid or expired credentials."
 *   - valid token                  -> HTTP 200 + a toast echoing the mapped claims
 */
class SsoCustomHeaderProviderSeeder extends Seeder
{
    public function run(): void
    {
        // An introspection / profile endpoint that returns the caller's identity
        // for a valid token. NOT a refresh/login endpoint — those have the wrong
        // semantics for identity resolution (a refresh call rotates tokens).
        $verifyUrl = env(
            'HASHTAGCMS_WORKFLOWS_SSO_VERIFY_URL',
            'https://api.example.com/user/profile'
        );

        WorkflowSsoProvider::updateOrCreate(
            ['alias' => 'custom-header-sso'],
            [
                'site_id'        => 1,
                'name'           => 'Custom-header SSO (example)',
                'description'    => 'Reads the token from the "authToken" header (strips "Bearer "), verifies it against a profile endpoint, and maps the response to an identity.',
                'driver'         => 'opaque',
                'enabled'        => true,
                'on_failure'     => 'reject', // invalid token -> 401 (vs. "anonymous" = run unauthenticated)
                'cache_ttl'      => 300,       // cache a verified token for 5 min (per sha256(token))
                'publish_status' => true,
                // Owner, so the row is visible to contributor accounts too (the admin
                // list filters non-admins by insert_by). Matches the UI's save behaviour.
                'insert_by'      => 1,
                'config'         => [
                    // 1. Where the token comes from. A configured header is
                    //    authoritative: no fallback to Authorization.
                    'credential' => [
                        'header'       => 'authToken',
                        'strip_prefix' => 'Bearer ', // note the trailing space
                    ],

                    // 2. How to verify it. The stripped token is {{request.bearer_token}};
                    //    other incoming headers are forwarded via {{request.headers.*}}.
                    //    NB: header names in {{request.headers.*}} are lower-cased
                    //    (e.g. the "storeId" header is {{request.headers.storeid}}).
                    'verify' => [
                        'url'     => $verifyUrl,
                        'method'  => 'GET',
                        'headers' => [
                            'authToken' => 'Bearer {{request.bearer_token}}',
                            'api_key'   => '{{request.headers.api_key}}',
                            'storeId'   => '{{request.headers.storeid}}',
                            'mobile'    => '{{request.headers.mobile}}',
                            'Accept'    => 'application/json',
                        ],
                        'timeout' => 15,
                    ],

                    // 3. Map the verify response into an identity. Adjust these
                    //    paths to your endpoint's actual JSON shape.
                    'identity' => [
                        'user_id' => '{{response.body.userId}}',
                        'claims'  => [
                            'mobile'  => '{{response.body.mobile}}',
                            'storeId' => '{{response.body.storeId}}',
                            'name'    => '{{response.body.name}}',
                        ],
                        // Opt-in raw passthrough -> {{ identity.raw.* }} in workflows.
                        // Carries the whole body verbatim (re-couples to the provider
                        // shape; may leak fields) — remove if you don't need it.
                        'raw' => '{{response.body}}',
                    ],
                ],
            ]
        );

        // Companion workflow: requires an authenticated identity, then echoes the
        // resolved claims so you can confirm the whole chain end-to-end.
        Workflow::updateOrCreate(
            ['alias' => 'WORKFLOW_WHOAMI_CUSTOM_HEADER'],
            [
                'site_id'        => 1,
                'name'           => 'Who am I (custom-header SSO test)',
                'description'    => 'Resolves the caller via the custom-header SSO provider and echoes the mapped claims. Requires a valid token.',
                'auth_required'  => true, // enforced -> 401 when the identity is anonymous
                // Pin the provider explicitly so this workflow always uses the
                // custom-header provider, even when the site has others enabled.
                'sso_provider_alias' => 'custom-header-sso',
                'handler'        => null,
                'publish_status' => true,
                'config'         => [
                    'version' => '1.0',
                    'target'  => ['type' => 'none'],
                    'on_success' => [
                        'message' => 'Identity resolved.',
                        'data'    => [
                            'external_user_id' => '{{ identity.external_user_id }}',
                            'provider'         => '{{ identity.provider }}',
                            'mobile'           => '{{ claims.mobile }}',
                            'storeId'          => '{{ claims.storeId }}',
                            'name'             => '{{ claims.name }}',
                        ],
                        'directives' => [
                            [
                                'type'    => 'toast',
                                'level'   => 'success',
                                'message' => 'Hello {{ claims.name | default: "there" }} (store {{ claims.storeId }})',
                            ],
                        ],
                    ],
                ],
            ]
        );
    }
}
