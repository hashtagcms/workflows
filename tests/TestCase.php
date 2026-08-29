<?php

namespace HashtagCms\Workflows\Tests;

use HashtagCms\Workflows\HashtagCmsWorkflowsServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            HashtagCmsWorkflowsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.debug', false);
        $app['config']->set('hashtagcms-workflows.master_site_id', 1);
    }
}
