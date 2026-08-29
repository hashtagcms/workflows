<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use HashtagCms\Workflows\Support\WorkflowModuleRegistry;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cms_modules', 'package')) {
            Schema::table('cms_modules', function (Blueprint $table) {
                $table->string('package', 100)->nullable()->after('icon_css');
            });
        }

        $modules = WorkflowModuleRegistry::definitions();
        $timestamp = now();

        // Map of definition slug => the actual (auto-incremented) cms_modules id,
        // so children can resolve their parent's real id without hardcoding it.
        $slugToId = [];

        foreach ($modules as $slug => $module) {
            $parentId = 0;
            if (!empty($module['parent'])) {
                // Parent must have been processed earlier (definitions are ordered
                // parents-first); fall back to a lookup by controller_name.
                $parentId = $slugToId[$module['parent']] ?? (int) DB::table('cms_modules')
                    ->where('controller_name', $modules[$module['parent']]['controller_name'] ?? '')
                    ->value('id');
            }

            $data = [
                'name' => $module['name'],
                'display_name' => $module['name'],
                'sub_title' => $module['description'] ?? null,
                'controller_name' => $module['controller_name'],
                'parent_id' => $parentId,
                'icon_css' => $module['icon'],
                'list_view_name' => $module['list_view_name'] ?? null,
                'edit_view_name' => $module['edit_view_name'] ?? null,
                'package' => WorkflowModuleRegistry::PACKAGE_NAME,
                // Position is written on both insert and update so the menu order
                // always reflects the registry (avoids stale/colliding positions
                // left behind by earlier installs).
                'position' => $module['position'] ?? 0,
                'updated_at' => $timestamp,
            ];

            // Upsert by controller_name (the natural key) — the id stays dynamic.
            $existing = DB::table('cms_modules')
                ->where('controller_name', $module['controller_name'])
                ->first();

            if ($existing) {
                DB::table('cms_modules')->where('id', $existing->id)->update($data);
                $id = $existing->id;
            } else {
                $data['created_at'] = $timestamp;
                $id = DB::table('cms_modules')->insertGetId($data);
            }

            $slugToId[$slug] = $id;

            DB::table('cms_permissions')->updateOrInsert(
                ['module_id' => $id, 'user_id' => 1],
                ['readonly' => 0]
            );
        }

        // Clean up modules retired from earlier versions (e.g. the classic
        // `workflows/manage`, replaced by the visual `workflows/builder`).
        $retired = ['workflows/manage'];
        $retiredIds = DB::table('cms_modules')->whereIn('controller_name', $retired)->pluck('id')->all();
        if (!empty($retiredIds)) {
            DB::table('cms_permissions')->whereIn('module_id', $retiredIds)->delete();
            DB::table('cms_modules')->whereIn('id', $retiredIds)->delete();
        }
    }

    public function down(): void
    {
        $controllerNames = array_column(WorkflowModuleRegistry::definitions(), 'controller_name');

        $ids = DB::table('cms_modules')
            ->whereIn('controller_name', $controllerNames)
            ->pluck('id')
            ->all();

        if (!empty($ids)) {
            DB::table('cms_permissions')->whereIn('module_id', $ids)->delete();
        }

        DB::table('cms_modules')->whereIn('controller_name', $controllerNames)->delete();
    }
};
