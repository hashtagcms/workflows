<?php

namespace HashtagCms\Workflows\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use HashtagCms\Core\Helpers\Message;
use HashtagCms\Workflows\Models\WorkflowSsoProvider;

/**
 * Admin CRUD for SSO / external-login providers (Workflows → SSO Providers).
 *
 * A provider row tells the SsoIdentityResolver how to verify a client credential
 * and map it to a workflow identity. The verification detail lives in the `config`
 * JSON (a `verify` request formatter + an `identity` response formatter); this
 * screen manages the row-level fields plus that JSON.
 */
class WorkflowSsoProviderController extends AdminWorkflowBaseController
{
    protected $dataFields = ['id', 'name', 'alias', 'driver', 'enabled', 'on_failure', 'publish_status', 'updated_at'];

    protected $dataSource = WorkflowSsoProvider::class;

    protected $actionFields = ['edit', 'delete'];

    public function store(Request $request)
    {
        if (!$this->checkPolicy('edit')) {
            return htcms_admin_view('common.error', Message::getWriteError());
        }

        $data = $request->all();
        $isEdit = ($data['actionPerformed'] ?? '') === 'edit';
        $editId = $data['id'] ?? null;
        $siteId = $data['site_id'] ?? htcms_get_siteId_for_admin();

        // Alias is unique per site (mirrors the workflows table), not globally.
        $aliasRule = Rule::unique('workflow_sso_providers', 'alias')->where('site_id', $siteId);
        if ($isEdit && $editId) {
            $aliasRule = $aliasRule->ignore($editId);
        }

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'alias' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', $aliasRule],
            'driver' => ['required', Rule::in(['opaque', 'jwt'])],
            'on_failure' => ['required', Rule::in(['reject', 'anonymous'])],
            'cache_ttl' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'config' => 'nullable',
            'enabled' => 'nullable|boolean',
            'publish_status' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $config = $this->decodeJsonField($data['config'] ?? null);
        if ($config === false) {
            return redirect()->back()
                ->withErrors(['config' => 'Invalid JSON in Provider Config: ' . json_last_error_msg()])
                ->withInput();
        }

        $saveData = [
            'site_id' => $siteId,
            'name' => $data['name'],
            'alias' => trim($data['alias']),
            'description' => $data['description'] ?? null,
            'driver' => $data['driver'],
            'on_failure' => $data['on_failure'],
            'cache_ttl' => isset($data['cache_ttl']) ? (int) $data['cache_ttl'] : 300,
            'config' => $config,
            'enabled' => isset($data['enabled']) ? (int) $data['enabled'] : 0,
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

        if ($isEdit) {
            $savedData = $this->saveData($arrSaveData, $editId);
        } else {
            $savedData = $this->saveData($arrSaveData);
        }

        $viewData['id'] = $savedData['id'];
        $viewData['saveData'] = $data;
        $viewData['backURL'] = $data['backURL'] ?? htcms_admin_path('workflows/sso');
        $viewData['isSaved'] = $savedData['isSaved'];

        return htcms_admin_view('common.saveinfo', $viewData);
    }

    /**
     * Decode a JSON text field to an array. Returns null for empty input,
     * false on malformed JSON, or the decoded array on success.
     *
     * @return array|null|false
     */
    protected function decodeJsonField($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        return $decoded;
    }
}
