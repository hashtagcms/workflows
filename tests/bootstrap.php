<?php

/**
 * Test bootstrap.
 *
 * The package's Eloquent models extend `HashtagCms\Models\AdminBaseModel`, which
 * lives in the heavyweight `hashtagcms/hashtagcms` core package. To keep the test
 * suite fast and self-contained, we alias that base class to a plain Eloquent model
 * WHEN core is not installed. When core *is* present, the real base class is used
 * and this alias is skipped, so the same tests run correctly in both environments.
 */

require __DIR__ . '/../vendor/autoload.php';

if (! class_exists(\HashtagCms\Models\AdminBaseModel::class, false)
    && ! class_exists(\HashtagCms\Models\AdminBaseModel::class)) {
    class_alias(\Illuminate\Database\Eloquent\Model::class, \HashtagCms\Models\AdminBaseModel::class);
}
