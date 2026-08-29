<?php

namespace HashtagCms\Workflows\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds the bundled example workflows. Safe to run repeatedly — each example
 * is upserted by its alias.
 *
 * Run from a host application with:
 *   php artisan db:seed --class="HashtagCms\Workflows\Database\Seeders\WorkflowExamplesSeeder"
 */
class WorkflowExamplesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Real HTTP GET examples (picsum.photos)
            LoadPhotosWorkflowSeeder::class,
            LoadPhotosPaginatedWorkflowSeeder::class,
            // Catalog of every structural pattern (direct, validation, http post,
            // service, event, php handler)
            WorkflowStructureExamplesSeeder::class,
            // Interactive builder demo (validation + target + data + directives)
            BuilderDemoWorkflowSeeder::class,
        ]);
    }
}
