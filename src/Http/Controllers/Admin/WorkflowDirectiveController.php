<?php

namespace HashtagCms\Workflows\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use HashtagCms\Core\Helpers\Message;
use HashtagCms\Workflows\Models\WorkflowDirective;

class WorkflowDirectiveController extends AdminWorkflowBaseController
{
    protected $dataFields = ['id', 'type', 'label', 'category', 'fallback', 'is_core', 'publish_status', 'updated_at'];

    protected $dataSource = WorkflowDirective::class;

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

        $typeRule = Rule::unique('workflow_directives', 'type')->where('site_id', $siteId);
        if ($isEdit && $editId) {
            $typeRule = $typeRule->ignore($editId);
        }

        $validator = Validator::make($data, [
            'type' => ['required', 'string', 'max:100', $typeRule],
            'label' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'platforms' => 'nullable',
            'schema' => 'nullable',
            'fallback' => 'nullable|string|max:100',
            'publish_status' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $platforms = $this->decodeJsonField($data['platforms'] ?? null);
        if ($platforms === false) {
            return redirect()->back()
                ->withErrors(['platforms' => 'Invalid JSON in Platforms map: ' . json_last_error_msg()])
                ->withInput();
        }

        $schema = $this->decodeJsonField($data['schema'] ?? null);
        if ($schema === false) {
            return redirect()->back()
                ->withErrors(['schema' => 'Invalid JSON in Payload Schema: ' . json_last_error_msg()])
                ->withInput();
        }

        $saveData = [
            'site_id' => $siteId,
            'type' => trim($data['type']),
            'label' => $data['label'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'platforms' => $platforms,
            'schema' => $schema,
            'fallback' => $data['fallback'] ?: null,
            'publish_status' => isset($data['publish_status']) ? (int)$data['publish_status'] : 1,
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
        $viewData['backURL'] = $data['backURL'] ?? htcms_admin_path('workflows/directives');
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
