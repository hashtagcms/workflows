<?php

namespace HashtagCms\Workflows;

use Illuminate\Support\ServiceProvider;
use HashtagCms\Workflows\Workflows;

class HashtagCmsWorkflowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Load helpers
        require_once __DIR__ . '/Helpers/WorkflowHelpers.php';

        $this->mergeConfigFrom(
            __DIR__ . '/../config/hashtagcms-workflows.php', 'hashtagcms-workflows'
        );

        $this->app->singleton('hashtagcms.workflows', function ($app) {
            return new Workflows();
        });
    }

    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        // Load views under hashtagcms-workflows namespace
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'hashtagcms-workflows');

        // Console commands + publishing
        if ($this->app->runningInConsole()) {
            $this->commands([
                \HashtagCms\Workflows\Console\MakeWorkflowCommand::class,
                \HashtagCms\Workflows\Console\PublishExamplesCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/hashtagcms-workflows.php' => config_path('hashtagcms-workflows.php'),
            ], 'hashtagcms-workflows-config');
        }
    }
}
