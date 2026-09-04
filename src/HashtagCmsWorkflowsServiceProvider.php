<?php

namespace HashtagCms\Workflows;

use Illuminate\Support\ServiceProvider;
use HashtagCms\Workflows\Workflows;
use HashtagCms\Workflows\Contracts\WorkflowIdentityResolver;
use HashtagCms\Workflows\Identity\AuthIdentityResolver;
use HashtagCms\Workflows\Identity\SsoIdentityResolver;
use HashtagCms\Workflows\Identity\Sso\SsoProviderRepository;

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

        // Identity resolver selection. When the SSO provider module is active
        // (its table exists and a provider is enabled), resolve identity through
        // the SSO-backed resolver; otherwise use the local Laravel guard exactly
        // as before. The check is guarded and only runs when the resolver is
        // built (once per execution), so installs without SSO pay nothing and
        // never hit the DB for it. Apps can still rebind this contract to their
        // own implementation to override both.
        $this->app->bind(WorkflowIdentityResolver::class, function ($app) {
            if ($app->make(SsoProviderRepository::class)->isModuleActive()) {
                return $app->make(SsoIdentityResolver::class);
            }

            return $app->make(AuthIdentityResolver::class);
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
                \HashtagCms\Workflows\Console\CheckJavaParityCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/hashtagcms-workflows.php' => config_path('hashtagcms-workflows.php'),
            ], 'hashtagcms-workflows-config');
        }
    }
}
