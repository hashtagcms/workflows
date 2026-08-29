<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use HashtagCms\Workflows\Database\Seeders\LoadPhotosWorkflowSeeder;
use HashtagCms\Workflows\Database\Seeders\LoadPhotosPaginatedWorkflowSeeder;
use HashtagCms\Workflows\Database\Seeders\WorkflowStructureExamplesSeeder;
use HashtagCms\Workflows\Database\Seeders\BuilderDemoWorkflowSeeder;

/**
 * Seeds the bundled example workflows on install so a fresh `php artisan migrate`
 * yields a fully working package: admin modules (000003) + directive manifest
 * (000004) + these example workflows — no separate `db:seed` step required.
 *
 * Each example is upserted by alias (idempotent). Opt out by setting
 * `hashtagcms-workflows.install.seed_examples` (env HASHTAGCMS_WORKFLOWS_SEED_EXAMPLES)
 * to false before migrating.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!config('hashtagcms-workflows.install.seed_examples', true)) {
            return;
        }

        // The workflows table must exist for the seeders to run.
        if (!Schema::hasTable('workflows')) {
            return;
        }

        foreach ([
            LoadPhotosWorkflowSeeder::class,
            LoadPhotosPaginatedWorkflowSeeder::class,
            WorkflowStructureExamplesSeeder::class,
            BuilderDemoWorkflowSeeder::class,
        ] as $seeder) {
            (new $seeder())->run();
        }
    }

    public function down(): void
    {
        // Example workflows are demo data; leave any admin edits intact on rollback.
    }
};
