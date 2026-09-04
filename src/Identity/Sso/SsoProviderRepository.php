<?php

namespace HashtagCms\Workflows\Identity\Sso;

use HashtagCms\Core\Scopes\SiteScope;
use HashtagCms\Workflows\Models\WorkflowSsoProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Selects the SSO provider that applies to a site, mirroring how
 * Workflows::execute() resolves a workflow: the site's own enabled+published
 * provider wins, otherwise the master site's, otherwise none.
 *
 * These lookups deliberately span sites (a site *and* its master fallback, and —
 * for `anyEnabled` — the whole install), so they run WITHOUT the model's
 * {@see SiteScope} global scope, which would otherwise pin every query to the
 * current admin/request site and defeat the master fallback and cross-site pins.
 */
class SsoProviderRepository
{
    /** A base query with the current-site global scope removed. */
    private function query(): Builder
    {
        return WorkflowSsoProvider::query()->withoutGlobalScope(SiteScope::class);
    }

    /**
     * The site's default provider: the site's own enabled+published row wins over
     * the master-site fallback. The secondary order by id keeps the pick
     * deterministic when a site has more than one enabled provider (in that case
     * pin the provider explicitly on the workflow — see byAlias / the editor).
     */
    public function forSite(int $siteId): ?WorkflowSsoProvider
    {
        $masterSiteId = (int) config('hashtagcms-workflows.master_site_id', 1);

        return $this->query()
            ->where('enabled', true)
            ->where('publish_status', true)
            ->where(function ($q) use ($siteId, $masterSiteId) {
                $q->where('site_id', $siteId)->orWhere('site_id', $masterSiteId);
            })
            ->orderByRaw('CASE WHEN site_id = ? THEN 0 ELSE 1 END', [$siteId])
            ->orderBy('id')
            ->first();
    }

    /**
     * A specific provider pinned by a workflow, resolved for the given site. Must
     * be enabled+published and applicable to the site (its own row or the master
     * fallback). Returns null when the alias is unknown/disabled for this site, so
     * the resolver can fall back to the site default rather than fail hard.
     */
    public function byAlias(string $alias, int $siteId): ?WorkflowSsoProvider
    {
        $masterSiteId = (int) config('hashtagcms-workflows.master_site_id', 1);

        return $this->query()
            ->where('alias', $alias)
            ->where('enabled', true)
            ->where('publish_status', true)
            ->where(function ($q) use ($siteId, $masterSiteId) {
                $q->where('site_id', $siteId)->orWhere('site_id', $masterSiteId);
            })
            ->orderByRaw('CASE WHEN site_id = ? THEN 0 ELSE 1 END', [$siteId])
            ->orderBy('id')
            ->first();
    }

    /**
     * All enabled+published providers a workflow on this site could pin (the site's
     * own plus the master fallback), site-first then by id. Powers the editor's
     * provider picker and its "which provider applies" indicator.
     *
     * @return Collection<int, WorkflowSsoProvider>
     */
    public function listForSite(int $siteId): Collection
    {
        $masterSiteId = (int) config('hashtagcms-workflows.master_site_id', 1);

        return $this->query()
            ->where('enabled', true)
            ->where('publish_status', true)
            ->where(function ($q) use ($siteId, $masterSiteId) {
                $q->where('site_id', $siteId)->orWhere('site_id', $masterSiteId);
            })
            ->orderByRaw('CASE WHEN site_id = ? THEN 0 ELSE 1 END', [$siteId])
            ->orderBy('id')
            ->get();
    }

    /** Whether any site has an enabled, published provider (fast existence check). */
    public function anyEnabled(): bool
    {
        return $this->query()
            ->where('enabled', true)
            ->where('publish_status', true)
            ->exists();
    }

    /**
     * Whether the SSO provider module is live: its table exists and at least one
     * provider is enabled. Guarded so it is safe to call before migrations run
     * (fresh install) or without the CMS DB — returns false rather than throwing.
     * Used to decide whether to swap in the SSO-backed identity resolver.
     */
    public function isModuleActive(): bool
    {
        try {
            return Schema::hasTable('workflow_sso_providers') && $this->anyEnabled();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
