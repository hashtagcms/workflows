<?php

namespace HashtagCms\Workflows\Models;

use HashtagCms\Models\AdminBaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use HashtagCms\Core\Scopes\SiteScope;

class WorkflowSsoProvider extends AdminBaseModel
{
    use SoftDeletes;

    protected $table = 'workflow_sso_providers';

    protected $guarded = [];

    // NB: `enabled` / `publish_status` are deliberately NOT cast to boolean. The
    // admin listing renders `publish_status` as the clickable publish icon only
    // when it serializes to the integer 1/0 that every other listed model emits;
    // a boolean `true`/`false` makes the icon disappear. SQL comparisons like
    // ->where('publish_status', true) work against the int column regardless.
    protected $casts = [
        'config' => 'array',
        'cache_ttl' => 'integer',
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
