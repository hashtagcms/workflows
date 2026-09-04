<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional per-workflow SSO provider pin.
 *
 * By default a workflow does not name a provider: identity is resolved by the
 * site's active SSO provider (see SsoProviderRepository::forSite). When a workflow
 * needs a *specific* provider — e.g. two providers are enabled on one site — set
 * `sso_provider_alias` to that provider's alias and the resolver uses it instead
 * of the site default. Null keeps the site-default behaviour.
 *
 * A string alias (not an id) is stored so it stays meaningful across environments
 * and matches how workflow_logs records `sso_provider_alias`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflows')) {
            return;
        }

        if (!Schema::hasColumn('workflows', 'sso_provider_alias')) {
            Schema::table('workflows', function (Blueprint $table) {
                $table->string('sso_provider_alias')->nullable()->after('auth_required');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('workflows') && Schema::hasColumn('workflows', 'sso_provider_alias')) {
            Schema::table('workflows', function (Blueprint $table) {
                $table->dropColumn('sso_provider_alias');
            });
        }
    }
};
