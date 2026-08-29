<?php

namespace HashtagCms\Workflows\Http\Controllers\Admin;

use Illuminate\Http\Request;
use HashtagCms\Workflows\Models\WorkflowLog;

class WorkflowLogController extends AdminWorkflowBaseController
{
    protected $dataFields = ['id', 'workflow_alias', 'is_success', 'execution_time_ms', 'created_at'];

    protected $dataSource = WorkflowLog::class;

    protected $moreActionFields = [
        [
            'label' => 'View Log Detail',
            'icon_css' => 'fa fa-info-circle',
            'action' => 'show',
            'action_append_field' => 'id',
        ],
    ];

    public function show($id)
    {
        $log = WorkflowLog::findOrFail($id);
        return htcms_workflows_view('logs.show', ['log' => $log]);
    }
}
