<?php

namespace HashtagCms\Workflows\Tests\Unit;

use HashtagCms\Workflows\Engine\VariableInterpolator;
use HashtagCms\Workflows\Tests\TestCase;

class VariableInterpolatorTest extends TestCase
{
    private array $context = [
        'payload' => [
            'item_id' => 42,
            'qty' => 3,
            'name' => 'Widget',
            'nested' => ['coupon' => 'SAVE10'],
            'items' => [1, 2, 3],
        ],
        'user' => ['id' => 7],
    ];

    public function test_single_placeholder_returns_native_type(): void
    {
        // A string that is exactly one placeholder should preserve the value's type.
        $this->assertSame(42, VariableInterpolator::interpolate('{{ payload.item_id }}', $this->context));
        $this->assertSame([1, 2, 3], VariableInterpolator::interpolate('{{ payload.items }}', $this->context));
    }

    public function test_embedded_placeholder_is_stringified(): void
    {
        $this->assertSame(
            'Order Widget x3',
            VariableInterpolator::interpolate('Order {{ payload.name }} x{{ payload.qty }}', $this->context)
        );
    }

    public function test_embedded_array_is_json_encoded(): void
    {
        $this->assertSame(
            'items: [1,2,3]',
            VariableInterpolator::interpolate('items: {{ payload.items }}', $this->context)
        );
    }

    public function test_dot_notation_resolves_nested_values(): void
    {
        $this->assertSame('SAVE10', VariableInterpolator::interpolate('{{ payload.nested.coupon }}', $this->context));
    }

    public function test_missing_value_without_default_is_null(): void
    {
        $this->assertNull(VariableInterpolator::interpolate('{{ payload.missing }}', $this->context));
    }

    public function test_default_filter_string(): void
    {
        $this->assertSame(
            'guest',
            VariableInterpolator::interpolate("{{ payload.missing | default: 'guest' }}", $this->context)
        );
    }

    public function test_default_filter_is_ignored_when_value_present(): void
    {
        $this->assertSame(
            42,
            VariableInterpolator::interpolate("{{ payload.item_id | default: 99 }}", $this->context)
        );
    }

    public function test_default_filter_numeric_and_boolean_and_null_literals(): void
    {
        $this->assertSame(1, VariableInterpolator::interpolate('{{ payload.missing | default: 1 }}', $this->context));
        $this->assertSame(1.5, VariableInterpolator::interpolate('{{ payload.missing | default: 1.5 }}', $this->context));
        $this->assertTrue(VariableInterpolator::interpolate('{{ payload.missing | default: true }}', $this->context));
        $this->assertFalse(VariableInterpolator::interpolate('{{ payload.missing | default: false }}', $this->context));
        $this->assertNull(VariableInterpolator::interpolate('{{ payload.missing | default: null }}', $this->context));
    }

    public function test_recurses_into_arrays_including_keys(): void
    {
        $template = [
            'title' => '{{ payload.name }}',
            'meta' => ['qty' => '{{ payload.qty }}'],
        ];

        $result = VariableInterpolator::interpolate($template, $this->context);

        $this->assertSame('Widget', $result['title']);
        $this->assertSame(3, $result['meta']['qty']);
    }

    public function test_non_string_scalars_pass_through_untouched(): void
    {
        $this->assertSame(123, VariableInterpolator::interpolate(123, $this->context));
        $this->assertTrue(VariableInterpolator::interpolate(true, $this->context));
    }

    public function test_env_lookup_with_default(): void
    {
        $this->assertSame(
            'fallback',
            VariableInterpolator::interpolate("{{ env.SOME_UNSET_WORKFLOW_VAR | default: 'fallback' }}", $this->context)
        );
    }
}
