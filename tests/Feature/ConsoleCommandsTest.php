<?php

namespace HashtagCms\Workflows\Tests\Feature;

use HashtagCms\Workflows\Tests\TestCase;
use Illuminate\Support\Facades\File;

class ConsoleCommandsTest extends TestCase
{
    private string $appPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Point app_path() at an isolated temp dir so generated files don't
        // pollute the testbench skeleton.
        $this->appPath = sys_get_temp_dir() . '/wf-console-' . uniqid();
        File::ensureDirectoryExists($this->appPath);
        $this->app->useAppPath($this->appPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->appPath);
        parent::tearDown();
    }

    public function test_make_workflow_creates_handler_with_derived_alias(): void
    {
        $this->artisan('make:workflow', ['name' => 'ApplyCouponWorkflow'])
            ->assertSuccessful();

        $path = $this->appPath . '/Workflows/ApplyCouponWorkflow.php';
        $this->assertFileExists($path);

        $contents = File::get($path);
        $this->assertStringContainsString('namespace App\\Workflows;', $contents);
        $this->assertStringContainsString('class ApplyCouponWorkflow implements WorkflowHandlerInterface', $contents);
        // Alias derived: ApplyCouponWorkflow -> WORKFLOW_APPLY_COUPON
        $this->assertStringContainsString("WORKFLOW_APPLY_COUPON", $contents);
    }

    public function test_make_workflow_respects_alias_option_and_nesting(): void
    {
        $this->artisan('make:workflow', ['name' => 'Cart/Reorder', '--alias' => 'WORKFLOW_REORDER'])
            ->assertSuccessful();

        $path = $this->appPath . '/Workflows/Cart/Reorder.php';
        $this->assertFileExists($path);

        $contents = File::get($path);
        $this->assertStringContainsString('namespace App\\Workflows\\Cart;', $contents);
        $this->assertStringContainsString('WORKFLOW_REORDER', $contents);
    }

    public function test_make_workflow_refuses_existing_without_force(): void
    {
        $this->artisan('make:workflow', ['name' => 'Dup'])->assertSuccessful();
        $this->artisan('make:workflow', ['name' => 'Dup'])->assertFailed();
        // With --force it succeeds.
        $this->artisan('make:workflow', ['name' => 'Dup', '--force' => true])->assertSuccessful();
    }

    public function test_publish_examples_writes_all_four_handlers(): void
    {
        $this->artisan('hashtagcms-workflows:publish-examples')->assertSuccessful();

        foreach (['AddToCartWorkflow', 'ApplyCouponWorkflow', 'QuickReorderWorkflow', 'SubmitFeedbackWorkflow'] as $class) {
            $path = $this->appPath . '/Workflows/Examples/' . $class . '.php';
            $this->assertFileExists($path);
            $this->assertStringContainsString('namespace App\\Workflows\\Examples;', File::get($path));
        }
    }

    public function test_publish_examples_skips_existing_without_force(): void
    {
        $this->artisan('hashtagcms-workflows:publish-examples')->assertSuccessful();

        // Second run should skip (files exist) but still succeed.
        $this->artisan('hashtagcms-workflows:publish-examples')
            ->expectsOutputToContain('Skipped (exists): app/Workflows/Examples/AddToCartWorkflow.php — use --force to overwrite.')
            ->assertSuccessful();
    }
}
