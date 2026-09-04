<?php

namespace HashtagCms\Workflows\Tests\Unit;

use HashtagCms\Workflows\Database\Seeders\LoginTestWorkflowSeeder;
use HashtagCms\Workflows\Tests\TestCase;
use HashtagCms\Workflows\Workflows;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Exercises the WORKFLOW_LOGIN_TEST seed through the real engine with the
 * external login endpoint faked — no live call, no real credentials.
 */
class LoginTestWorkflowTest extends TestCase
{
    private const LOGIN_URL = 'http://packages.testing:8003/api/hashtagcms/public/user/v1/login';

    protected function setUp(): void
    {
        parent::setUp();
        config(['hashtagcms-workflows.negotiation.enabled' => false]);
        putenv('HASHTAGCMS_WORKFLOWS_TEST_LOGIN_URL=' . self::LOGIN_URL);

        foreach (['workflows', 'workflow_logs'] as $t) {
            if (Schema::hasTable($t)) {
                continue;
            }
            Schema::create($t, function (Blueprint $table) use ($t) {
                $table->id();
                if ($t === 'workflows') {
                    $table->unsignedBigInteger('site_id')->default(1);
                    $table->string('name');
                    $table->string('alias');
                    $table->text('description')->nullable();
                    $table->boolean('auth_required')->default(false);
                    $table->string('handler')->nullable();
                    $table->json('config')->nullable();
                    $table->boolean('publish_status')->default(true);
                    $table->timestamps();
                    $table->softDeletes();
                } else {
                    $table->string('workflow_alias');
                    $table->unsignedBigInteger('site_id')->default(1);
                    $table->unsignedBigInteger('user_id')->nullable();
                    $table->string('external_user_id')->nullable();
                    $table->string('sso_provider_alias')->nullable();
                    $table->json('payload')->nullable();
                    $table->json('response_directives')->nullable();
                    $table->boolean('is_success')->default(true);
                    $table->string('error_message')->nullable();
                    $table->integer('execution_time_ms')->default(0);
                    $table->string('client_platform')->nullable();
                    $table->string('client_app_version')->nullable();
                    $table->json('negotiation')->nullable();
                    $table->timestamps();
                }
            });
        }

        (new LoginTestWorkflowSeeder())->run();
    }

    public function test_successful_login_returns_token_and_user(): void
    {
        Http::fake([
            self::LOGIN_URL => Http::response([
                'user' => ['id' => 7, 'email' => 'test@example.com', 'name' => 'Test'],
                'token' => [
                    'access_token' => '42|abc123def456',
                    'expires_at' => '2026-12-31 23:59:59',
                ],
            ], 200),
        ]);

        $response = (new Workflows())->execute('WORKFLOW_LOGIN_TEST', [
            'email' => 'test@example.com',
            'password' => 'secret-not-real',
        ]);

        $result = $response->toArray();
        $this->assertTrue($result['success']);
        $this->assertSame('42|abc123def456', $result['data']['token']);
        $this->assertSame('2026-12-31 23:59:59', $result['data']['expires_at']);
        $this->assertSame(7, $result['data']['user']['id']);
        $toasts = array_filter(
            $result['directives'],
            fn ($d) => ($d['type'] ?? null) === 'toast' && ($d['message'] ?? null) === 'Welcome back!'
        );
        $this->assertNotEmpty($toasts, 'expected a "Welcome back!" success toast');

        // Credentials were forwarded to the external endpoint in the POST body.
        Http::assertSent(function ($request) {
            return $request->url() === self::LOGIN_URL
                && $request['email'] === 'test@example.com'
                && $request['password'] === 'secret-not-real';
        });
    }

    public function test_bad_credentials_surface_the_api_message(): void
    {
        Http::fake([
            self::LOGIN_URL => Http::response(['message' => 'Email or password is incorrect.'], 401),
        ]);

        $response = (new Workflows())->execute('WORKFLOW_LOGIN_TEST', [
            'email' => 'test@example.com',
            'password' => 'wrong',
        ]);

        $result = $response->toArray();
        $this->assertFalse($result['success']);
        $this->assertSame('Email or password is incorrect.', $result['message']);
    }

    public function test_validation_blocks_empty_input_without_calling_the_api(): void
    {
        Http::fake();

        $response = (new Workflows())->execute('WORKFLOW_LOGIN_TEST', ['email' => 'not-an-email']);

        $this->assertFalse($response->toArray()['success']);
        Http::assertNothingSent();
    }
}
