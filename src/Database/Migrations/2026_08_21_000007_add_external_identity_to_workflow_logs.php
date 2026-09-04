<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records externally-resolved identities on workflow_logs.
 *
 * `user_id` stays the LOCAL integer user id (Laravel guard / local users table).
 * When login is handled by another service, the subject may be a string/UUID
 * with no local row — captured here as `external_user_id`, along with the
 * `sso_provider_alias` that resolved it. Non-destructive: existing rows and the
 * `user_id` column are untouched. See the SSO provider module (workflow_sso_providers).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_logs')) {
            return;
        }

        Schema::table('workflow_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('workflow_logs', 'external_user_id')) {
                $table->string('external_user_id')->nullable()->index()->after('user_id');
            }
            if (!Schema::hasColumn('workflow_logs', 'sso_provider_alias')) {
                $table->string('sso_provider_alias')->nullable()->after('external_user_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('workflow_logs')) {
            return;
        }

        Schema::table('workflow_logs', function (Blueprint $table) {
            foreach (['external_user_id', 'sso_provider_alias'] as $column) {
                if (Schema::hasColumn('workflow_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
