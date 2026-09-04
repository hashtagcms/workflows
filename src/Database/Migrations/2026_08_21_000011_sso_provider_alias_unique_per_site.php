<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make `workflow_sso_providers.alias` unique **per site** instead of globally.
 *
 * The table originally shipped with a global unique index on `alias`. Aliases are
 * now scoped per site (a site + its master fallback), mirroring the `workflows`
 * table, so two different sites may reuse an alias. This converts existing
 * installs: drop the old single-column unique, add a composite `(site_id, alias)`
 * unique. Fresh installs are already born composite (see the create migration),
 * so this is a guarded no-op there.
 */
return new class extends Migration
{
    private string $table = 'workflow_sso_providers';
    private string $oldIndex = 'workflow_sso_providers_alias_unique';
    private string $newIndex = 'workflow_sso_providers_site_alias_unique';

    public function up(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        [$hasGlobalAlias, $hasComposite] = $this->indexState();

        if ($hasGlobalAlias) {
            Schema::table($this->table, fn (Blueprint $t) => $t->dropUnique($this->oldIndex));
        }

        if (! $hasComposite) {
            Schema::table($this->table, function (Blueprint $t) {
                // Guard the add too: on drivers where index introspection is
                // unavailable we may not have detected an existing composite.
                try {
                    $t->unique(['site_id', 'alias'], $this->newIndex);
                } catch (\Throwable $e) {
                    // Already present — nothing to do.
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        [, $hasComposite] = $this->indexState();

        if ($hasComposite) {
            Schema::table($this->table, fn (Blueprint $t) => $t->dropUnique($this->newIndex));
        }

        Schema::table($this->table, function (Blueprint $t) {
            try {
                $t->unique('alias', $this->oldIndex);
            } catch (\Throwable $e) {
                // A duplicate alias across sites can legitimately block restoring
                // the global unique — leave it off rather than fail the rollback.
            }
        });
    }

    /**
     * @return array{0: bool, 1: bool} [hasGlobalAliasUnique, hasCompositeUnique].
     * Falls back to [false, false] where the schema index API is unavailable, so
     * up() relies on its try/catch guards instead.
     */
    private function indexState(): array
    {
        $hasGlobalAlias = false;
        $hasComposite = false;

        try {
            foreach (Schema::getIndexes($this->table) as $idx) {
                $cols = $idx['columns'] ?? [];
                if (! empty($idx['unique']) && $cols === ['alias']) {
                    $hasGlobalAlias = true;
                }
                if (! empty($idx['unique']) && $cols === ['site_id', 'alias']) {
                    $hasComposite = true;
                }
            }
        } catch (\Throwable $e) {
            // Older schema API without getIndexes(): leave both false.
        }

        return [$hasGlobalAlias, $hasComposite];
    }
};
