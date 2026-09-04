<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use HashtagCms\Workflows\Support\WorkflowModuleRegistry;

/**
 * Registers the "SSO Providers" admin module in cms_modules for installs that
 * already ran 000003 (which seeds the full registry). Fresh installs get it from
 * 000003 directly; this migration is the idempotent catch-up for existing ones.
 * Upserts by controller_name so re-running never duplicates.
 */
return new class extends Migration
{
    private const SLUG = 'sso';

    public function up(): void
    {
        if (! Schema::hasTable('cms_modules')) {
            return; // CMS core not installed (e.g. isolated package tests).
        }

        $definitions = WorkflowModuleRegistry::definitions();
        $module = $definitions[self::SLUG] ?? null;
        if ($module === null) {
            return;
        }

        // Resolve the parent group's real (auto-incremented) id by its controller_name.
        $parentControllerName = $definitions[$module['parent']]['controller_name'] ?? '';
        $parentId = (int) DB::table('cms_modules')
            ->where('controller_name', $parentControllerName)
            ->value('id');

        $timestamp = now();
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
            'position' => $module['position'] ?? 0,
            'updated_at' => $timestamp,
        ];

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

        DB::table('cms_permissions')->updateOrInsert(
            ['module_id' => $id, 'user_id' => 1],
            ['readonly' => 0]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_modules')) {
            return;
        }

        $controllerName = WorkflowModuleRegistry::definitions()[self::SLUG]['controller_name'] ?? null;
        if ($controllerName === null) {
            return;
        }

        $id = DB::table('cms_modules')->where('controller_name', $controllerName)->value('id');
        if ($id) {
            DB::table('cms_permissions')->where('module_id', $id)->delete();
            DB::table('cms_modules')->where('id', $id)->delete();
        }
    }
};
