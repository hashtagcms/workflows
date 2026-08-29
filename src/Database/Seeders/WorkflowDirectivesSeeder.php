<?php

namespace HashtagCms\Workflows\Database\Seeders;

use Illuminate\Database\Seeder;
use HashtagCms\Workflows\Models\WorkflowDirective;
use HashtagCms\Workflows\Support\DirectiveManifest;

/**
 * (Re)seeds the core directive capability manifest onto the master site.
 *
 * The migration already seeds these rows on install; this seeder exists so the
 * catalog can be refreshed on demand or re-applied after manual edits:
 *
 *   php artisan db:seed --class="HashtagCms\Workflows\Database\Seeders\WorkflowDirectivesSeeder"
 *
 * Idempotent — each directive is upserted by (site_id, type) and never touches
 * rows a host application added itself.
 */
class WorkflowDirectivesSeeder extends Seeder
{
    public function run(): void
    {
        $masterSiteId = (int) config('hashtagcms-workflows.master_site_id', 1);

        foreach (DirectiveManifest::core() as $directive) {
            WorkflowDirective::updateOrCreate(
                ['site_id' => $masterSiteId, 'type' => $directive['type']],
                [
                    'label'          => $directive['label'],
                    'category'       => $directive['category'] ?? null,
                    'description'    => $directive['description'] ?? null,
                    'platforms'      => $directive['platforms'] ?? null,
                    'schema'         => $directive['schema'] ?? null,
                    'fallback'       => $directive['fallback'] ?? null,
                    'is_core'        => true,
                    'publish_status' => true,
                ]
            );
        }
    }
}
