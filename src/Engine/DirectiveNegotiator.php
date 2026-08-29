<?php

namespace HashtagCms\Workflows\Engine;

use HashtagCms\Workflows\Models\WorkflowDirective;
use Illuminate\Support\Collection;

/**
 * Capability negotiation: rewrites a workflow's emitted directive list so a
 * client only ever receives directives it can actually render.
 *
 * For each emitted directive:
 *   - supported                         -> kept unchanged
 *   - unsupported but has a `fallback`  -> replaced with the fallback (chased
 *                                          along the chain until it lands on a
 *                                          supported type, cycle-guarded)
 *   - unsupported, no usable fallback   -> dropped
 *
 * Fail-safe by design:
 *   - a directive `type` that is not in the manifest at all is passed through
 *     (we cannot reason about what we do not know), so an empty or not-yet-seeded
 *     manifest is a no-op;
 *   - any error resolving the manifest (e.g. the table does not exist yet)
 *     degrades to passing every directive through unchanged.
 *
 * See docs/12-directive-capability-negotiation.md.
 */
class DirectiveNegotiator
{
    /** Hard cap on how far a fallback chain is followed before giving up. */
    protected const MAX_FALLBACK_DEPTH = 5;

    /**
     * Negotiate a directive list for a specific client.
     *
     * @param array<int, array<string, mixed>> $directives Emitted directives.
     * @param int         $siteId
     * @param string|null $platform    e.g. 'web' | 'android' | 'ios'
     * @param string|null $appVersion  e.g. '2.3.0' (null = unknown, assume latest)
     * @param array<int, string> $capabilities Explicit override: when non-empty,
     *        exactly these directive types are considered supported (version
     *        resolution is skipped). Useful for feature flags / A-B builds.
     *
     * @return array{directives: array<int, array<string,mixed>>, downgraded: array<int, array{from:string,to:string}>, dropped: array<int, string>}
     */
    public function negotiate(
        array $directives,
        int $siteId,
        ?string $platform,
        ?string $appVersion = null,
        array $capabilities = []
    ): array {
        $manifest = $this->resolveManifest($siteId);

        // Nothing to negotiate against — pass everything through untouched.
        if ($manifest->isEmpty()) {
            return ['directives' => $directives, 'downgraded' => [], 'dropped' => []];
        }

        $out = [];
        $downgraded = [];
        $dropped = [];

        foreach ($directives as $directive) {
            $type = is_array($directive) ? ($directive['type'] ?? null) : null;

            // Not a recognised directive shape, or a type outside the manifest:
            // we can't reason about it, so keep it as-is.
            if ($type === null || !$manifest->has($type)) {
                $out[] = $directive;
                continue;
            }

            if ($this->isSupported($type, $manifest, $platform, $appVersion, $capabilities)) {
                $out[] = $directive;
                continue;
            }

            // Unsupported — follow the fallback chain.
            $resolved = $this->resolveFallback($type, $manifest, $platform, $appVersion, $capabilities);

            if ($resolved !== null) {
                $out[] = ['type' => $resolved];
                $downgraded[] = ['from' => $type, 'to' => $resolved];
            } else {
                $dropped[] = $type;
            }
        }

        return ['directives' => $out, 'downgraded' => $downgraded, 'dropped' => $dropped];
    }

    /**
     * Resolve the effective directive manifest for a site: rows for the site
     * itself plus the master site as a fallback, with the site-specific row
     * winning per `type`. Keyed by `type`.
     *
     * @return Collection<string, WorkflowDirective>
     */
    public function resolveManifest(int $siteId): Collection
    {
        $masterSiteId = (int) config('hashtagcms-workflows.master_site_id', 1);

        try {
            $rows = WorkflowDirective::query()
                ->where('publish_status', true)
                ->whereIn('site_id', array_unique([$siteId, $masterSiteId]))
                ->get();
        } catch (\Throwable $e) {
            // Table missing / DB unavailable — degrade to "no manifest".
            return collect();
        }

        return $rows
            ->groupBy('type')
            ->map(fn ($group) => $group->firstWhere('site_id', $siteId) ?? $group->first());
    }

    /**
     * The resolved directive catalog for a site as plain arrays, suitable for
     * the manifest API. When both $platform and $appVersion are provided the
     * list is pre-filtered to the client's supported set.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalog(int $siteId, ?string $platform = null, ?string $appVersion = null): array
    {
        $manifest = $this->resolveManifest($siteId);

        $filterToClient = $platform !== null && $platform !== '';

        return $manifest
            ->when($filterToClient, fn ($m) => $m->filter(
                fn ($d) => $this->isSupported($d->type, $manifest, $platform, $appVersion, [])
            ))
            ->map(fn ($d) => [
                'type'        => $d->type,
                'label'       => $d->label,
                'category'    => $d->category,
                'description' => $d->description,
                'platforms'   => $d->platforms,
                'schema'      => $d->schema,
                'fallback'    => $d->fallback,
            ])
            ->values()
            ->all();
    }

    /**
     * Is this directive type renderable by the given client?
     *
     * @param Collection<string, WorkflowDirective> $manifest
     */
    protected function isSupported(
        string $type,
        Collection $manifest,
        ?string $platform,
        ?string $appVersion,
        array $capabilities
    ): bool {
        // Explicit capability list overrides version-based resolution.
        if (!empty($capabilities)) {
            return in_array($type, $capabilities, true);
        }

        $directive = $manifest->get($type);
        if (!$directive) {
            // Unknown to the manifest = pass through (handled by caller too).
            return true;
        }

        $platforms = $directive->platforms;

        // Null / empty platforms = universally supported.
        if (empty($platforms) || !is_array($platforms)) {
            return true;
        }

        // No platform info from the client — can't exclude by platform; assume ok.
        if ($platform === null || $platform === '') {
            return true;
        }

        // Directive not listed for this platform at all = unsupported here.
        if (!array_key_exists($platform, $platforms)) {
            return false;
        }

        $minVersion = $platforms[$platform];

        // Listed with no minimum, or client version unknown = assume supported.
        if ($minVersion === null || $minVersion === '' || $appVersion === null || $appVersion === '') {
            return true;
        }

        return version_compare($appVersion, (string) $minVersion, '>=');
    }

    /**
     * Walk the fallback chain from an unsupported type to the first supported
     * type. Returns null if the chain dead-ends, loops, or exceeds the depth cap.
     *
     * @param Collection<string, WorkflowDirective> $manifest
     */
    protected function resolveFallback(
        string $type,
        Collection $manifest,
        ?string $platform,
        ?string $appVersion,
        array $capabilities
    ): ?string {
        $seen = [$type => true];
        $current = $type;

        for ($depth = 0; $depth < self::MAX_FALLBACK_DEPTH; $depth++) {
            $directive = $manifest->get($current);
            $next = $directive->fallback ?? null;

            if (empty($next) || isset($seen[$next])) {
                return null; // dead end or cycle
            }

            $seen[$next] = true;

            // A fallback outside the manifest is assumed renderable (we can't
            // constrain it), so it is a valid landing point.
            if (!$manifest->has($next)) {
                return $next;
            }

            if ($this->isSupported($next, $manifest, $platform, $appVersion, $capabilities)) {
                return $next;
            }

            $current = $next;
        }

        return null;
    }
}
