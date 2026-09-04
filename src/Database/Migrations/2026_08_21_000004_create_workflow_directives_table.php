<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use HashtagCms\Workflows\Support\DirectiveManifest;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_directives')) {
            Schema::create('workflow_directives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('site_id')->default(1)->index();
                $table->string('type');                      // canonical name: 'mutate_cart'
                $table->string('label');                     // human name for admin UI
                $table->string('category')->nullable();      // 'cart' | 'navigation' | 'feedback'
                $table->text('description')->nullable();
                $table->json('platforms')->nullable();       // { "web":"1.0", "android":"2.1", "ios":"2.1" }
                $table->json('schema')->nullable();          // payload field spec (used for validation)
                $table->string('fallback')->nullable();      // another directive `type` to substitute
                $table->boolean('is_core')->default(false);  // package-shipped vs app-registered
                $table->boolean('publish_status')->default(true);
                $table->unsignedBigInteger('insert_by')->nullable();
                $table->unsignedBigInteger('update_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['site_id', 'type']);         // type is unique *within a site*
            });
        }

        // Seed the core directive manifest onto the master site, upserting by
        // (site_id, type) so re-runs are idempotent and never clobber rows a host
        // application added itself. Mirrors how cms_modules rows are seeded.
        $masterSiteId = (int) config('hashtagcms-workflows.master_site_id', 1);
        $timestamp = now();

        foreach (DirectiveManifest::core() as $directive) {
            $data = [
                'label'          => $directive['label'],
                'category'       => $directive['category'] ?? null,
                'description'    => $directive['description'] ?? null,
                'platforms'      => isset($directive['platforms']) ? json_encode($directive['platforms']) : null,
                'schema'         => isset($directive['schema']) ? json_encode($directive['schema']) : null,
                'fallback'       => $directive['fallback'] ?? null,
                'is_core'        => true,
                'publish_status' => true,
                'updated_at'     => $timestamp,
            ];

            $existing = DB::table('workflow_directives')
                ->where('site_id', $masterSiteId)
                ->where('type', $directive['type'])
                ->first();

            if ($existing) {
                DB::table('workflow_directives')->where('id', $existing->id)->update($data);
            } else {
                $data['site_id'] = $masterSiteId;
                $data['type'] = $directive['type'];
                $data['created_at'] = $timestamp;
                DB::table('workflow_directives')->insert($data);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_directives');
    }
};
