<?php

namespace HashtagCms\Workflows\Console;

use Illuminate\Console\Command;
use HashtagCms\Workflows\Console\Concerns\InteractsWithStubs;

class PublishExamplesCommand extends Command
{
    use InteractsWithStubs;

    protected $signature = 'hashtagcms-workflows:publish-examples {--force : Overwrite files that already exist}';

    protected $description = 'Publish the example workflow handlers into app/Workflows/Examples';

    /** Example class => its conventional alias. */
    protected array $examples = [
        'AddToCartWorkflow' => 'WORKFLOW_ADD_TO_CART',
        'ApplyCouponWorkflow' => 'WORKFLOW_APPLY_COUPON',
        'QuickReorderWorkflow' => 'WORKFLOW_QUICK_REORDER',
        'SubmitFeedbackWorkflow' => 'WORKFLOW_SUBMIT_FEEDBACK',
    ];

    public function handle(): int
    {
        $namespace = $this->appNamespaceRoot() . '\\Workflows\\Examples';
        $force = (bool) $this->option('force');

        $created = [];
        foreach ($this->examples as $class => $alias) {
            $path = app_path('Workflows/Examples/' . $class . '.php');
            $content = $this->renderStub('examples/' . $class . '.stub', ['namespace' => $namespace]);

            if ($this->writeFile($path, $content, $force)) {
                $this->info("Created: app/Workflows/Examples/{$class}.php");
                $created[$class] = $alias;
            } else {
                $this->warn("Skipped (exists): app/Workflows/Examples/{$class}.php — use --force to overwrite.");
            }
        }

        if (!empty($created)) {
            $this->newLine();
            $this->line('Register the published examples in a service provider (e.g. AppServiceProvider::boot):');
            foreach ($created as $class => $alias) {
                $this->line("    \\HashtagCms\\Workflows\\Facades\\Workflows::register('{$alias}', \\{$namespace}\\{$class}::class);");
            }
        }

        return self::SUCCESS;
    }
}
