<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SSO / external-login providers, managed in the admin (Workflows → SSO Providers).
 *
 * Mirrors the `workflows` table conventions: per-site with a master-site fallback,
 * a stable `alias`, and a `config` JSON that drives verification. There is
 * deliberately NO foreign key to a users table — a provider resolves an external
 * subject that may have no local row (see workflow_logs.external_user_id).
 *
 * `config` shape depends on `driver`:
 *   opaque -> { "verify": { url, method, headers, body … }, "identity": { user_id, claims } }
 *   jwt    -> { "jwks_url", "issuer", "audience", "identity": { user_id, claims } }
 * The `verify`/`identity` blocks reuse the HttpTargetAdapter + VariableInterpolator
 * request/response-formatting shape ({{request.*}}, {{token.*}}, {{response.*}}).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workflow_sso_providers')) {
            return;
        }

        Schema::create('workflow_sso_providers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->default(1)->index();
            $table->string('name');
            // Alias is unique per site (a site + its master fallback), mirroring the
            // `workflows` table — not globally — so different sites may reuse an alias.
            $table->string('alias');
            $table->text('description')->nullable();
            $table->enum('driver', ['jwt', 'opaque'])->default('opaque');
            $table->boolean('enabled')->default(true)->index();
            $table->json('config')->nullable();
            $table->enum('on_failure', ['reject', 'anonymous'])->default('reject');
            $table->unsignedInteger('cache_ttl')->default(300);
            $table->boolean('publish_status')->default(true);
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['site_id', 'alias'], 'workflow_sso_providers_site_alias_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_sso_providers');
    }
};
