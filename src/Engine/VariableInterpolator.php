<?php

namespace HashtagCms\Workflows\Engine;

use Illuminate\Support\Arr;

class VariableInterpolator
{
    /**
     * Interpolate variables in any data structure (string, array, scalar).
     *
     * @param mixed $template
     * @param array $context
     * @return mixed
     */
    public static function interpolate(mixed $template, array $context): mixed
    {
        if (is_array($template)) {
            $result = [];
            foreach ($template as $key => $val) {
                $interpolatedKey = is_string($key) ? self::interpolateString($key, $context) : $key;
                $result[$interpolatedKey] = self::interpolate($val, $context);
            }
            return $result;
        }

        if (is_string($template)) {
            return self::interpolateString($template, $context);
        }

        return $template;
    }

    /**
     * Interpolate a single template string.
     *
     * @param string $template
     * @param array $context
     * @return mixed
     */
    public static function interpolateString(string $template, array $context): mixed
    {
        // Pattern matches {{ variable }} or {{ variable | default: 'value' }}
        $pattern = '/\{\{\s*([a-zA-Z0-9_\.\-]+(?:\s*\|\s*default\s*:\s*(?:(?:\'[^\']*\')|(?:"[^"]*")|[^\}\s]+))?)\s*\}\}/';

        // Check if the entire string is just a single placeholder: e.g. "{{ payload.items }}"
        if (preg_match('/^\{\{\s*([a-zA-Z0-9_\.\-]+(?:\s*\|\s*default\s*:\s*(?:(?:\'[^\']*\')|(?:"[^"]*")|[^\}\s]+))?)\s*\}\}$/', trim($template), $matches)) {
            return self::resolveExpression($matches[1], $context);
        }

        // Multiple placeholders or embedded within a larger string
        return preg_replace_callback($pattern, function ($matches) use ($context) {
            $val = self::resolveExpression($matches[1], $context);
            if (is_array($val) || is_object($val)) {
                return json_encode($val);
            }
            return (string)$val;
        }, $template);
    }

    /**
     * Resolve an individual expression like "payload.item_id" or "payload.qty | default: 1".
     *
     * @param string $expression
     * @param array $context
     * @return mixed
     */
    public static function resolveExpression(string $expression, array $context): mixed
    {
        $parts = explode('|', $expression, 2);
        $varPath = trim($parts[0]);
        $defaultVal = null;
        $hasDefault = false;

        if (isset($parts[1])) {
            $filter = trim($parts[1]);
            if (preg_match('/^default\s*:\s*(.*)$/', $filter, $defMatches)) {
                $hasDefault = true;
                $rawDef = trim($defMatches[1]);
                $defaultVal = self::parseLiteralValue($rawDef);
            }
        }

        // Check for env variables: {{ env.KEY_NAME }}
        if (str_starts_with($varPath, 'env.')) {
            $envKey = substr($varPath, 4);
            $val = env($envKey);
            return $val !== null ? $val : ($hasDefault ? $defaultVal : null);
        }

        // Dot notation retrieval from context
        $val = Arr::get($context, $varPath);

        if ($val === null && $hasDefault) {
            return $defaultVal;
        }

        return $val;
    }

    /**
     * Parse literal values like 'string', "string", 123, true, false, null.
     *
     * @param string $raw
     * @return mixed
     */
    protected static function parseLiteralValue(string $raw): mixed
    {
        $raw = trim($raw);

        // Quoted string 'hello' or "hello"
        if ((str_starts_with($raw, "'") && str_ends_with($raw, "'")) ||
            (str_starts_with($raw, '"') && str_ends_with($raw, '"'))) {
            return substr($raw, 1, -1);
        }

        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float)$raw : (int)$raw;
        }

        $lower = strtolower($raw);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if ($lower === 'null') return null;

        return $raw;
    }
}
