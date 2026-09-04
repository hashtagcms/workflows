<?php

namespace HashtagCms\Workflows\Models;

use HashtagCms\Models\AdminBaseModel;
use HashtagCms\Core\Scopes\SiteScope;

class Workflow extends AdminBaseModel
{
    protected $table = 'workflows';

    protected $guarded = [];

    protected $casts = [        
        'config' => 'array'
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
