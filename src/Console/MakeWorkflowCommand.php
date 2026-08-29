<?php

namespace HashtagCms\Workflows\Console;

use Illuminate\Console\Command;
use HashtagCms\Workflows\Console\Concerns\InteractsWithStubs;

class MakeWorkflowCommand extends Command
{
    use InteractsWithStubs;

    protected $signature = 'make:workflow
        {name : Class name, optionally nested (e.g. ApplyCoupon or Cart/ApplyCoupon)}
        {--alias= : Workflow alias (defaults to one derived from the class name)}
        {--force : Overwrite the file if it already exists}';

    protected $description = 'Scaffold a new server-driven workflow handler in app/Workflows';

    public function handle(): int
    {
        $name = str_replace('\\', '/', trim((string) $this->argument('name')));
        $parts = array_values(array_filter(explode('/', $name)));

        if (empty($parts)) {
            $this->error('A workflow name is required.');
            return self::FAILURE;
        }

        $class = array_pop($parts);
        $subNamespace = implode('\\', $parts);            // '' or 'Cart' or 'Cart\Promo'
        $subPath = $parts ? implode('/', $parts) . '/' : '';

        $namespace = $this->appNamespaceRoot() . '\\Workflows' . ($subNamespace ? '\\' . $subNamespace : '');
        $alias = $this->option('alias') ?: $this->deriveAlias($class);

        $path = app_path('Workflows/' . $subPath . $class . '.php');

        $content = $this->renderStub('workflow.stub', [
            'namespace' => $namespace,
            'class' => $class,
            'alias' => $alias,
        ]);

        if (!$this->writeFile($path, $content, (bool) $this->option('force'))) {
            $this->error("Workflow already exists: {$path} (use --force to overwrite).");
            return self::FAILURE;
        }

        $this->info("Created workflow: {$path}");
        $this->newLine();
        $this->line('Register it in a service provider (e.g. AppServiceProvider::boot):');
        $this->line("    \\HashtagCms\\Workflows\\Facades\\Workflows::register('{$alias}', \\{$namespace}\\{$class}::class);");

        return self::SUCCESS;
    }

    /** Derive an alias like WORKFLOW_APPLY_COUPON from a class like ApplyCouponWorkflow. */
    protected function deriveAlias(string $class): string
    {
        $base = preg_replace('/Workflow$/', '', $class);
        $snake = strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $base));
        return 'WORKFLOW_' . trim($snake, '_');
    }
}
