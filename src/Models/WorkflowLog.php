<?php

namespace HashtagCms\Workflows\Models;

use HashtagCms\Models\AdminBaseModel;
use HashtagCms\Core\Scopes\SiteScope;

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

    /**
     * @override
     * boot
     */
    protected static function boot()
    {

        parent::boot();
        static::addGlobalScope(new SiteScope);
    }
}
