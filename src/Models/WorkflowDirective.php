<?php

namespace HashtagCms\Workflows\Models;

use HashtagCms\Models\AdminBaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowDirective extends AdminBaseModel
{
    use SoftDeletes;

    protected $table = 'workflow_directives';

    protected $guarded = [];

    protected $casts = [
        'platforms'      => 'array',
        'schema'         => 'array',
        'is_core'        => 'boolean',
        'publish_status' => 'boolean',
    ];
}
