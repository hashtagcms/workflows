<?php

namespace HashtagCms\Workflows\Support;

use HashtagCms\Workflows\Models\Workflow;

/**
 * Derives a machine-readable contract for the workflows configured on a site:
 * for each workflow, its alias, the payload keys it expects (from
 * `config.validation.rules`), and the directive types it can emit (from
 * `config.on_success` / `on_failure` / top-level `directives`).
 *
 * This is the workflow analogue of {@see DirectiveManifest} — the single source
 * of truth a client can fetch to validate `ExecuteWorkflow` calls (alias exists,
 * required inputs present) and catch client/server drift early. Nothing is
 * hand-maintained: the catalog is introspected from the seeded workflow configs.
 */
class WorkflowCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forSite(int $siteId): array
    {
        return Workflow::query()
            ->where('site_id', $siteId)
            ->where('publish_status', 1)
            ->orderBy('alias')
            ->get()
            ->map(fn ($wf) => self::describe($wf))
            ->values()
            ->all();
    }

    /**
     * @param  \HashtagCms\Workflows\Models\Workflow  $wf
     * @return array<string, mixed>
     */
    private static function describe($wf): array
    {
        $config = is_string($wf->config) ? json_decode($wf->config, true) : (array) $wf->config;
        $config = is_array($config) ? $config : [];

        // Expected payload keys, from validation rules.
        $rules = $config['validation']['rules'] ?? ($config['rules'] ?? []);
        $inputs = [];
        foreach ($rules as $key => $rule) {
            $ruleStr = is_array($rule) ? implode('|', $rule) : (string) $rule;
            $inputs[$key] = [
                'required' => str_contains($ruleStr, 'required'),
                'rule'     => $ruleStr,
            ];
        }

        // Directive types this workflow can emit.
        $types = [];
        foreach (['on_success', 'on_failure'] as $branch) {
            foreach (($config[$branch]['directives'] ?? []) as $d) {
                if (isset($d['type'])) {
                    $types[] = $d['type'];
                }
            }
        }
        foreach (($config['directives'] ?? []) as $d) {
            if (isset($d['type'])) {
                $types[] = $d['type'];
            }
        }

        return [
            'alias'         => $wf->alias,
            'name'          => $wf->name,
            'description'   => $wf->description,
            'auth_required' => (bool) ($wf->auth_required ?? false),
            // (object) so an empty input set serializes as {} not [] — keeps the JSON
            // shape stable for typed clients that decode `inputs` as a map.
            'inputs'        => (object) $inputs,
            'emits'         => array_values(array_unique($types)),
            'target'        => $config['target']['type'] ?? null,
        ];
    }
}
