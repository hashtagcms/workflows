<?php

namespace HashtagCms\Workflows\Console\Concerns;

use Illuminate\Support\Facades\File;

trait InteractsWithStubs
{
    /** Absolute path to a stub file shipped with the package. */
    protected function stubPath(string $name): string
    {
        return __DIR__ . '/../../../stubs/' . ltrim($name, '/');
    }

    /**
     * Render a stub, replacing {{ key }} placeholders.
     *
     * @param array<string, string> $replacements
     */
    protected function renderStub(string $stub, array $replacements): string
    {
        $content = File::get($this->stubPath($stub));
        foreach ($replacements as $key => $value) {
            $content = str_replace('{{ ' . $key . ' }}', $value, $content);
        }
        return $content;
    }

    /** Write $content to $path. Returns false if it exists and $force is false. */
    protected function writeFile(string $path, string $content, bool $force): bool
    {
        if (File::exists($path) && !$force) {
            return false;
        }
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
        return true;
    }

    /** The application's root namespace without trailing slash (e.g. "App"). */
    protected function appNamespaceRoot(): string
    {
        try {
            return trim($this->laravel->getNamespace(), '\\');
        } catch (\Throwable $e) {
            // getNamespace() can fail when the app path is customised or the
            // composer.json PSR-4 map can't be resolved; fall back to the
            // Laravel default.
            return 'App';
        }
    }
}
