<?php

declare(strict_types=1);

namespace Framework\Console;

use Framework\OpenApi\OpenApiGenerator;

/**
 * Precompile the OpenAPI JSON spec to a static file. Production deploy step:
 *
 *   bin/console openapi:dump           # writes var/cache/openapi.json
 *   bin/console openapi:dump --clear   # removes the cache file
 *
 * The {@see \Framework\Controllers\Api\V1\DocsController} reads the file at
 * runtime when present, dropping the per-request scan cost to a `file_get_contents()`.
 */
final class OpenApiDumpCommand implements Command
{
    public function __construct(
        private readonly OpenApiGenerator $generator,
        private readonly string $cacheFile,
    ) {
    }

    public function name(): string
    {
        return 'openapi:dump';
    }

    public function description(): string
    {
        return 'Generates (or clears with --clear) the precompiled OpenAPI JSON spec.';
    }

    public function run(array $argv): int
    {
        $clear = false;
        foreach ($argv as $arg) {
            if ($arg === '--clear') {
                $clear = true;
            } else {
                fwrite(STDERR, "Unknown argument: {$arg}\n");
                return 1;
            }
        }

        if ($clear) {
            if (is_file($this->cacheFile)) {
                @unlink($this->cacheFile);
            }
            echo "Cleared OpenAPI cache: {$this->cacheFile}\n";
            return 0;
        }

        $dir = dirname($this->cacheFile);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            fwrite(STDERR, "Cannot create directory: {$dir}\n");
            return 1;
        }

        $json = $this->generator->scan();
        $written = @file_put_contents($this->cacheFile, $json);
        if ($written === false) {
            fwrite(STDERR, "Cannot write: {$this->cacheFile}\n");
            return 1;
        }

        echo "Wrote " . strlen($json) . " bytes to {$this->cacheFile}\n";
        return 0;
    }
}
