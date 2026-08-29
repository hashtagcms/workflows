<?php

namespace HashtagCms\Workflows\Http\Controllers\Admin;

use HashtagCms\Workflows\Models\Workflow;

/**
 * Admin "Workflow Playground".
 *
 * A read-only demo screen that lists the published workflows (so it reflects
 * whatever has been seeded) and lets an admin run each one against the public
 * execute endpoint, watching the returned directives render and inspecting the
 * raw request/response JSON.
 */
class PlaygroundController extends AdminWorkflowBaseController
{
    /**
     * Curated sample payloads for the bundled example workflows. Any workflow
     * not listed here falls back to keys derived from its own config.
     */
    private array $curatedSamples = [
        'WORKFLOW_LOAD_PHOTOS'         => [],
        'WORKFLOW_LOAD_PHOTOS_PAGED'   => ['page' => 2, 'limit' => 4],
        'WORKFLOW_EXAMPLE_DIRECT'      => ['name' => 'Sam'],
        'WORKFLOW_EXAMPLE_VALIDATION'  => ['email' => 'user@example.com', 'age' => 21],
        'WORKFLOW_EXAMPLE_HTTP_POST'   => ['title' => 'Hello', 'body' => 'World'],
        'WORKFLOW_EXAMPLE_SERVICE'     => ['sku' => 'ABC-9', 'quantity' => 2],
        'WORKFLOW_EXAMPLE_EVENT'       => ['message' => 'ping'],
        'WORKFLOW_EXAMPLE_HANDLER'     => ['name' => 'Sam'],
    ];

    public function index($more = null)
    {
        $siteId = htcms_get_siteId_for_admin();

        $workflows = Workflow::query()
            ->where('publish_status', true)
            ->where(function ($q) use ($siteId) {
                $q->where('site_id', $siteId)
                    ->orWhere('site_id', (int) config('hashtagcms-workflows.master_site_id', 1));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'alias', 'description', 'config']);

        $examples = $workflows->map(function ($w) {
            return [
                'alias'          => $w->alias,
                'name'           => $w->name,
                'description'    => $w->description,
                'sample_payload' => $this->samplePayload($w),
            ];
        })->values();

        $executeUrl = url(trim(config('hashtagcmsapi.route_prefix', 'api/hashtagcms'), '/') . '/public/workflows/v1/execute');

        // Resolve the view from this module's cms_modules row (package + list_view_name)
        // the same way the core admin CRUD does — no package-specific view helper.
        return htcms_admin_view($this->getViewNames(request()->module_info, 'listing'), [
            'examples'   => $examples,
            'executeUrl' => $executeUrl,
            'siteId'     => $siteId,
        ]);
    }

    /**
     * Best-effort sample payload: a curated one for known examples, otherwise
     * keys derived from `{{ payload.* }}` placeholders in the workflow config.
     */
    private function samplePayload($workflow): string
    {
        if (array_key_exists($workflow->alias, $this->curatedSamples)) {
            return json_encode($this->curatedSamples[$workflow->alias], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $config = $workflow->config;
        $json = is_array($config) ? json_encode($config) : (string) $config;

        preg_match_all('/\{\{\s*payload\.([a-zA-Z0-9_]+)/', (string) $json, $matches);
        $keys = array_values(array_unique($matches[1] ?? []));

        if (empty($keys)) {
            return '{}';
        }

        $sample = [];
        foreach ($keys as $key) {
            $sample[$key] = '';
        }

        return json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
