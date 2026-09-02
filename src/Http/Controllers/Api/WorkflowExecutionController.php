<?php

namespace HashtagCms\Workflows\Http\Controllers\Api;

use Illuminate\Http\Request;
use HashtagCms\Workflows\Http\Controllers\Controller;
use HashtagCms\Workflows\Facades\Workflows;
use HashtagCms\Workflows\Engine\DirectiveNegotiator;

class WorkflowExecutionController extends Controller
{
    public function execute(Request $request)
    {
        $alias = $request->input('workflow');
        $payload = $request->input('payload', []);
        $siteId = (int)$request->input('site_id', 1);

        // Client identity for capability negotiation. Prefer the richer
        // `client: { platform, app_version }` object; fall back to the legacy
        // top-level `platform` for backward compatibility.
        $platform = $request->input('client.platform', $request->input('platform', 'android'));
        $appVersion = $request->input('client.app_version');
        $capabilities = (array) $request->input('capabilities', []);

        if (empty($alias)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required parameter: workflow',
                'directives' => []
            ], 400);
        }

        try {
            $response = Workflows::execute(
                alias: $alias,
                payload: $payload,
                siteId: $siteId,
                platform: $platform,
                appVersion: $appVersion,
                capabilities: $capabilities
            );

            return response()->json($response->toArray());

        } catch (\Throwable $e) {
            report($e);

            // Never leak internal error details in production. Expose the raw
            // exception message only when explicitly enabled, defaulting to
            // the framework's debug flag.
            $exposeDetails = config('hashtagcms-workflows.expose_error_details');
            $exposeDetails = $exposeDetails === null
                ? (bool) config('app.debug', false)
                : (bool) $exposeDetails;

            $clientMessage = $exposeDetails
                ? $e->getMessage()
                : 'Workflow execution failed. Please try again later.';

            return response()->json([
                'success' => false,
                'message' => $clientMessage,
                'directives' => [
                    [
                        'type' => 'toast',
                        'message' => 'Workflow failed: ' . $clientMessage,
                        'level' => 'error'
                    ]
                ]
            ], 500);
        }
    }

    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'module' => 'hashtagcms/workflows',
            'registered_workflows' => array_keys(Workflows::getRegistered())
        ]);
    }

    /**
     * The directive capability manifest for a site. With `platform` (and
     * optionally `app_version`) query params the list is pre-filtered to that
     * client's supported set, so a client can cache "what can I render" at
     * startup. See docs/12-directive-capability-negotiation.md.
     */
    public function directives(Request $request)
    {
        $siteId = (int) $request->query('site_id', 1);
        $platform = $request->query('platform');
        $appVersion = $request->query('app_version');

        $negotiator = app()->make(DirectiveNegotiator::class);

        return response()->json([
            'success' => true,
            'directives' => $negotiator->catalog($siteId, $platform, $appVersion),
        ]);
    }

    /**
     * The workflow contract for a site: each workflow's alias, expected payload
     * keys (from validation rules), and the directive types it can emit. Lets a
     * client validate ExecuteWorkflow calls and detect alias/payload drift.
     * See {@see \HashtagCms\Workflows\Support\WorkflowCatalog}.
     */
    public function catalog(Request $request)
    {
        $siteId = (int) $request->query('site_id', 1);

        return response()->json([
            'success' => true,
            'site_id' => $siteId,
            'workflows' => \HashtagCms\Workflows\Support\WorkflowCatalog::forSite($siteId),
        ]);
    }
}
