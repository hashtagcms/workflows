<?php

return [
    'page_title' => 'HashtagCMS Workflows',

    'enabled' => env('HASHTAGCMS_WORKFLOWS_ENABLED', true),

    /**
     * The "master" site id used as a fallback when a site-specific workflow
     * is not found. In stock HashtagCMS the master site is 1; override here
     * (or via env) if your installation uses a different master site id.
     */
    'master_site_id' => (int) env('HASHTAGCMS_WORKFLOWS_MASTER_SITE_ID', 1),

    /**
     * When true, workflow execution failures return the raw exception message
     * to the API client. Defaults to app.debug so production responses never
     * leak internal error details. Set explicitly to override.
     */
    'expose_error_details' => env('HASHTAGCMS_WORKFLOWS_EXPOSE_ERRORS', null),

    'logging' => [
        'enabled' => true,
        'prune_days' => 30
    ],

    /**
     * Install behaviour. When `seed_examples` is true (default), running
     * `php artisan migrate` also seeds the bundled example workflows so a fresh
     * install is immediately usable end-to-end. Set to false to migrate the
     * schema and admin modules without the demo workflows.
     */
    'install' => [
        'seed_examples' => env('HASHTAGCMS_WORKFLOWS_SEED_EXAMPLES', true),
    ],

    /**
     * Directive capability negotiation. When enabled, the execution engine
     * rewrites each response's directive list against the `workflow_directives`
     * manifest so a client only receives directives it can render (unsupported
     * ones are downgraded to their fallback or dropped). Fail-safe: unknown
     * directive types and an empty manifest pass straight through.
     * See docs/12-directive-capability-negotiation.md.
     */
    'negotiation' => [
        'enabled' => env('HASHTAGCMS_WORKFLOWS_NEGOTIATION', true),
    ],

    'middleware' => ['web', 'auth:sanctum', 'cmsModuleInfo', 'cmsInterceptor'],

    /**
     * Base route prefix for Workflows admin module.
     * When null, defaults dynamically to {hashtagcmsadmin.cmsInfo.base_path}/workflows
     */
    'route_prefix' => env('HASHTAGCMS_WORKFLOWS_ROUTE_PREFIX', null),

    'view_prefix' => 'hashtagcms-workflows::be.workflows',
];
