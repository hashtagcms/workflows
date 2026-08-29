<?php

namespace HashtagCms\Workflows\Models;

use HashtagCms\Models\AdminBaseModel;

class Workflow extends AdminBaseModel
{
    protected $table = 'workflows';

    protected $guarded = [];

    protected $casts = [        
        'config' => 'array'
    ];
}
