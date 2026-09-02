<?php

namespace HashtagCms\Workflows\Console;

use HashtagCms\Workflows\Support\DirectiveManifest;
use Illuminate\Console\Command;

/**
 * Tells you when the Java port has fallen behind this (PHP, reference)
 * implementation's directive manifest.
 *
 * The Java repo (github.com/hashtagcms/workflows-java) keeps a checked-in fixture
 * `src/test/resources/php-directive-manifest.json` generated from this manifest,
 * and a `DirectiveManifestParityTest` that fails if the Java `DirectiveManifest`
 * has drifted from that fixture. This command is the PHP-side half of the loop:
 * it compares THIS manifest against that fixture and reports anything the Java
 * side still needs to pick up (a directive added / removed / renamed here, or a
 * category / per-platform support / fallback / label / description change).
 *
 *   php artisan workflows:check-java-parity
 *   php artisan workflows:check-java-parity --java-repo=../business-java/workflows
 *   php artisan workflows:check-java-parity --write   # update the Java repo's fixture from PHP
 *
 * Exit code is non-zero on drift, so it drops straight into CI.
 */
class CheckJavaParityCommand extends Command
{
    protected $signature = 'workflows:check-java-parity
        {--java-repo= : Path to the Java repo checkout (default: ../business-java/workflows relative to this package)}
        {--fixture= : Path to the fixture JSON directly (overrides --java-repo)}
        {--write : Rewrite the fixture from the current PHP manifest instead of comparing}';

    protected $description = 'Check whether the Java port is behind the PHP directive manifest (or --write to sync the fixture)';

    public function handle(): int
    {
        $fixturePath = $this->resolveFixturePath();

        if ($this->option('write')) {
            $json = $this->encode($this->canonicalManifest());
            if (!is_dir(dirname($fixturePath)) && !@mkdir(dirname($fixturePath), 0777, true)) {
                $this->error("Cannot create fixture directory: " . dirname($fixturePath));
                return self::FAILURE;
            }
            file_put_contents($fixturePath, $json);
            $this->info("Wrote PHP directive manifest to: {$fixturePath}");
            $this->line('Now run the Java parity test to see if the Java DirectiveManifest needs updating:');
            $this->line('    ./mvnw test -Dtest=DirectiveManifestParityTest');
            return self::SUCCESS;
        }

        if (!is_file($fixturePath)) {
            $this->error("Java fixture not found: {$fixturePath}");
            $this->line('Point at the Java repo with --java-repo=PATH, or pass --fixture=PATH.');
            return self::FAILURE;
        }

        $php  = $this->indexByType($this->canonicalManifest());
        $java = $this->indexByType(json_decode((string) file_get_contents($fixturePath), true) ?: []);

        $missingInJava = array_values(array_diff(array_keys($php), array_keys($java)));  // PHP has, Java lacks
        $staleInJava   = array_values(array_diff(array_keys($java), array_keys($php)));  // Java has, PHP dropped
        $changed = [];
        foreach ($php as $type => $entry) {
            if (isset($java[$type]) && $java[$type] !== $entry) {
                $changed[] = $type;
            }
        }

        if (!$missingInJava && !$staleInJava && !$changed) {
            $this->info("✓ Java is in sync with the PHP directive manifest ({" . count($php) . "} directives).");
            return self::SUCCESS;
        }

        $this->warn('Java is BEHIND the PHP directive manifest:');
        $this->newLine();
        if ($missingInJava) {
            $this->line('  Missing in Java (added here, not yet ported):');
            foreach ($missingInJava as $t) $this->line("    + {$t}");
        }
        if ($staleInJava) {
            $this->line('  Stale in Java (removed/renamed here):');
            foreach ($staleInJava as $t) $this->line("    - {$t}");
        }
        if ($changed) {
            $this->line('  Changed (fields differ between PHP and Java):');
            foreach ($changed as $t) {
                $fields = $this->changedFields($php[$t], $java[$t]);
                $this->line("    ~ {$t}: " . implode(', ', $fields));
            }
        }
        $this->newLine();
        $this->line('Sync the fixture, then update the Java DirectiveManifest:');
        $this->line('    php artisan workflows:check-java-parity --write');
        $this->line('    (in the Java repo) ./mvnw test -Dtest=DirectiveManifestParityTest');

        return self::FAILURE;
    }

    /** Canonical shape — identical to scripts/dump-php-manifest.php in the Java repo. */
    private function canonicalManifest(): array
    {
        $out = [];
        foreach (DirectiveManifest::core() as $d) {
            $out[] = [
                'type'        => $d['type'] ?? null,
                'label'       => $d['label'] ?? null,
                'category'    => $d['category'] ?? null,
                'description' => $d['description'] ?? null,
                'platforms'   => $d['platforms'] ?? null,
                'fallback'    => $d['fallback'] ?? null,
            ];
        }
        return $out;
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function indexByType(array $list): array
    {
        $byType = [];
        foreach ($list as $entry) {
            if (isset($entry['type'])) {
                $byType[$entry['type']] = $entry;
            }
        }
        ksort($byType);
        return $byType;
    }

    /** @return string[] names of fields that differ */
    private function changedFields(array $php, array $java): array
    {
        $diff = [];
        foreach ($php as $k => $v) {
            if (($java[$k] ?? null) !== $v) {
                $diff[] = $k;
            }
        }
        return $diff ?: ['(shape)'];
    }

    private function resolveFixturePath(): string
    {
        if ($this->option('fixture')) {
            return (string) $this->option('fixture');
        }
        $packageRoot = dirname(__DIR__, 2);
        $javaRepo = $this->option('java-repo')
            ?: $packageRoot . '/../business-java/workflows';
        return rtrim((string) $javaRepo, '/') . '/src/test/resources/php-directive-manifest.json';
    }
}
