<?php

namespace HashtagCms\Workflows\Tests\Unit;

use HashtagCms\Workflows\Engine\WorkflowResponse;
use HashtagCms\Workflows\Tests\TestCase;

class WorkflowResponseTest extends TestCase
{
    public function test_defaults_to_success(): void
    {
        $response = WorkflowResponse::make();

        $this->assertTrue($response->getSuccess());
        $this->assertNull($response->getMessage());
        $this->assertSame([], $response->getDirectives());
    }

    public function test_toast_directive_shape(): void
    {
        $response = WorkflowResponse::make()->toast('Saved', 'success');

        $this->assertSame(
            [['type' => 'toast', 'message' => 'Saved', 'level' => 'success']],
            $response->getDirectives()
        );
    }

    public function test_add_toast_is_alias_of_toast(): void
    {
        $a = WorkflowResponse::make()->toast('x', 'error')->getDirectives();
        $b = WorkflowResponse::make()->addToast('x', 'error')->getDirectives();

        $this->assertSame($a, $b);
    }

    public function test_mutate_cart_merges_type(): void
    {
        $directives = WorkflowResponse::make()->mutateCart(['count' => 2])->getDirectives();

        $this->assertSame([['type' => 'mutate_cart', 'count' => 2]], $directives);
    }

    public function test_navigate_open_sheet_and_haptic(): void
    {
        $response = WorkflowResponse::make()
            ->navigate('/cart', ['ref' => 1])
            ->openSheet('sheet-1', ['a' => 'b'])
            ->haptic('medium');

        $this->assertSame([
            ['type' => 'navigate', 'target' => '/cart', 'params' => ['ref' => 1]],
            ['type' => 'open_sheet', 'sheetId' => 'sheet-1', 'payload' => ['a' => 'b']],
            ['type' => 'haptic', 'intensity' => 'medium'],
        ], $response->getDirectives());
    }

    public function test_set_success_with_message(): void
    {
        $response = WorkflowResponse::make()->setSuccess(false, 'nope');

        $this->assertFalse($response->getSuccess());
        $this->assertSame('nope', $response->getMessage());
    }

    public function test_to_array_full_shape(): void
    {
        $array = WorkflowResponse::make()
            ->setSuccess(true, 'ok')
            ->withData(['id' => 9])
            ->toast('hi')
            ->toArray();

        $this->assertSame([
            'success' => true,
            'message' => 'ok',
            'directives' => [['type' => 'toast', 'message' => 'hi', 'level' => 'success']],
            'data' => ['id' => 9],
        ], $array);
    }

    public function test_with_data_merges(): void
    {
        $array = WorkflowResponse::make()
            ->withData(['a' => 1])
            ->withData(['b' => 2])
            ->toArray();

        $this->assertSame(['a' => 1, 'b' => 2], $array['data']);
    }
}
