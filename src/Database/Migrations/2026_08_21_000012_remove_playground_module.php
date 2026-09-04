<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the retired "Workflow Playground" admin module from installs that
 * seeded it earlier (module 000003 / 000009-era). The Playground page, its route,
 * controller and view have been deleted from the package; this drops its
 * `cms_modules` row and per-user `cms_permissions` so the stale menu item and its
 * grants disappear. Idempotent — a no-op when the row is already gone.
 *
 * The controller_name is hard-coded (not read from WorkflowModuleRegistry) on
 * purpose: the registry entry has been removed, so there is nothing to look up.
 */
return new class extends Migration
{
    private const CONTROLLER_NAME = 'workflows/playground';

    public function up(): void
    {
        if (! Schema::hasTable('cms_modules')) {
            return; // CMS core not installed (e.g. isolated package tests).
        }

        $id = DB::table('cms_modules')
            ->where('controller_name', self::CONTROLLER_NAME)
            ->value('id');

        if ($id) {
            if (Schema::hasTable('cms_permissions')) {
                DB::table('cms_permissions')->where('module_id', $id)->delete();
            }
            DB::table('cms_modules')->where('id', $id)->delete();
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: the Playground module was removed from the
        // package (no route/controller/view remain), so there is nothing to
        // restore. Re-add it manually if it is ever reintroduced.
    }
};
