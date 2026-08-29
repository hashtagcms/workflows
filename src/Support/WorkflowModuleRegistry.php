<?php

namespace HashtagCms\Workflows\Support;

class WorkflowModuleRegistry
{
    const PACKAGE_NAME = 'hashtagcms-workflows';

    /**
     * Admin menu modules contributed by this package.
     *
     * Keyed by a stable slug (NOT a database id). Parents are referenced by slug
     * via the `parent` key; the seeding migration resolves those to the real,
     * auto-incremented `cms_modules.id` at install time so ids never clash with
     * core or other packages. `controller_name` is the natural upsert key.
     */
    public static function definitions(): array
    {
        return [
            'group' => [
                'name' => 'Workflows',
                'controller_name' => 'workflows/home',
                'parent' => null, // top-level menu group
                'icon' => 'fa fa-random',
                'description' => 'Server-driven workflow and action orchestration',
                'list_view_name' => null,
                'edit_view_name' => null,
                'position' => 60,
            ],
            'directives' => [
                'name' => 'Workflow Directives',
                'controller_name' => 'workflows/directives',
                'parent' => 'group',
                'icon' => 'fa fa-plug',
                'description' => 'Capability manifest of client directives and per-platform support',
                'list_view_name' => null,
                // Core prefixes this via the `package` column → hashtagcms-workflows::workflows.directives.addedit
                'edit_view_name' => 'workflows/directives/addedit',
                'position' => 62,
            ],
            'logs' => [
                'name' => 'Workflow Logs',
                'controller_name' => 'workflows/logs',
                'parent' => 'group',
                'icon' => 'fa fa-list-alt',
                'description' => 'Audit logs and execution times of workflows',
                'list_view_name' => null,
                'edit_view_name' => null,
                'position' => 63,
            ],
            'playground' => [
                'name' => 'Workflow Playground',
                'controller_name' => 'workflows/playground',
                'parent' => 'group',
                'icon' => 'fa fa-flask',
                'description' => 'Run the seeded example workflows and watch directives render',
                // Core resolves this via the `package` column → hashtagcms-workflows::workflows.playground.index
                'list_view_name' => 'workflows/playground/index',
                'edit_view_name' => null,
                'position' => 64,
            ],
            'builder' => [
                'name' => 'Workflow Manager',
                'controller_name' => 'workflows/builder',
                'parent' => 'group',
                'icon' => 'fa fa-sitemap',
                'description' => 'Create and configure workflows with the visual builder',
                'list_view_name' => null,
                // Core prefixes this via the `package` column → hashtagcms-workflows::workflows.builder.index
                'edit_view_name' => 'workflows/builder/index',
                'position' => 61,
            ],
        ];
    }
}
