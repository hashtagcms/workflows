<?php

namespace HashtagCms\Workflows\Models;

use HashtagCms\Models\AdminBaseModel;

class WorkflowLog extends AdminBaseModel
{
    protected $table = 'workflow_logs';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'response_directives' => 'array',
        'negotiation' => 'array',
        'is_success' => 'boolean'
    ];
}
