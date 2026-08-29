<?php

namespace HashtagCms\Workflows\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use HashtagCms\Workflows\Models\Workflow;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\GenericWorkflowEngine;
use HashtagCms\Workflows\Engine\DirectiveNegotiator;

/**
 * Workflow Manager — the Vue-based visual editor for `workflows` rows. Listing,
 * create, and edit are inherited from the core admin CRUD base (the module's
 * edit view is the Vue mount blade). The save path answers the builder's AJAX
 * request with JSON, and `preview()` runs a transient config through the engine.
 */
class WorkflowBuilderController extends AdminWorkflowBaseController
{
    protected $dataFields = ['id', 'name', 'alias', 'publish_status', 'updated_at'];

    protected $dataSource = Workflow::class;

    protected $actionFields = ['edit', 'delete'];

    /**
     * Persist a workflow from the builder's AJAX request and return JSON.
     * Writes the identical `workflows` row shape as the classic manager, so the
     * two editors remain fully interchangeable.
     */
    public function store(Request $request)
    {
        if (!$this->checkPolicy('edit')) {
            return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
        }

        $data = $request->all();
        $isEdit = ($data['actionPerformed'] ?? '') === 'edit';
        $editId = $data['id'] ?? null;
        $siteId = $data['site_id'] ?? htcms_get_siteId_for_admin();

        $aliasRule = Rule::unique('workflows', 'alias')->where('site_id', $siteId);
        if ($isEdit && $editId) {
            $aliasRule = $aliasRule->ignore($editId);
        }

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'alias' => ['required', 'string', 'max:100', $aliasRule],
            'description' => 'nullable|string',
            'handler' => 'nullable|string|max:255',
            'config' => 'nullable',
            'auth_required' => 'nullable|boolean',
            'publish_status' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $configData = null;
        if (!empty($data['config'])) {
            if (is_string($data['config'])) {
                $decoded = json_decode($data['config'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid config JSON: ' . json_last_error_msg(),
                    ], 422);
                }
                $configData = $decoded;
            } elseif (is_array($data['config'])) {
                $configData = $data['config'];
            }
        }

        $saveData = [
            'site_id' => $siteId,
            'name' => $data['name'],
            'alias' => strtoupper(trim($data['alias'])),
            'description' => $data['description'] ?? null,
            'handler' => !empty($data['handler']) ? trim($data['handler']) : null,
            'config' => $configData,
            'auth_required' => isset($data['auth_required']) ? (int) $data['auth_required'] : 0,
            'publish_status' => isset($data['publish_status']) ? (int) $data['publish_status'] : 1,
            'updated_at' => htcms_get_current_date(),
        ];

        if (!$isEdit) {
            $saveData['created_at'] = htcms_get_current_date();
            $saveData['insert_by'] = auth()->id() ?? 1;
        } else {
            $saveData['update_by'] = auth()->id() ?? 1;
        }

        $arrSaveData = ['model' => $this->dataSource, 'data' => $saveData];
        $savedData = $isEdit ? $this->saveData($arrSaveData, $editId) : $this->saveData($arrSaveData);

        return response()->json([
            'success' => (bool) ($savedData['isSaved'] ?? true),
            'id' => $savedData['id'] ?? $editId,
            'message' => 'Workflow saved.',
        ]);
    }

    /**
     * Run the builder's current (unsaved) config through the engine for a live
     * preview — no persistence. Mirrors the real execute path (declarative
     * engine + capability negotiation) on a transient workflow.
     */
    public function preview(Request $request)
    {
        $config = $request->input('config', []);
        if (is_string($config)) {
            $config = json_decode($config, true) ?: [];
        }
        $payload = (array) $request->input('payload', []);
        $platform = $request->input('platform');
        $appVersion = $request->input('app_version');
        $siteId = (int) ($request->input('site_id') ?: (htcms_get_siteId_for_admin() ?: 1));

        try {
            $workflow = new Workflow([
                'name' => 'Preview',
                'alias' => 'WORKFLOW_PREVIEW',
                'site_id' => $siteId,
                'config' => is_array($config) ? $config : [],
            ]);

            $context = new WorkflowContext(
                workflow: $workflow,
                payload: $payload,
                siteId: $siteId,
                platform: $platform,
                user: auth()->user(),
                appVersion: $appVersion
            );

            $response = app()->make(GenericWorkflowEngine::class)->execute($context);

            if (config('hashtagcms-workflows.negotiation.enabled', true)) {
                $negotiator = app()->make(DirectiveNegotiator::class);
                $result = $negotiator->negotiate($response->getDirectives(), $siteId, $platform, $appVersion);
                $response->setDirectives($result['directives']);
            }

            return response()->json($response->toArray());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'directives' => [],
            ]);
        }
    }
}
