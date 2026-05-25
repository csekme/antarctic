<?php

declare(strict_types=1);

namespace Framework\OpenApi;

use OpenApi\Generator;

/**
 * Scans configured source paths for `OA\*` attributes and produces an
 * OpenAPI 3.x JSON document. Production deployments precompile the JSON via
 * `bin/console openapi:dump` to `var/cache/openapi.json`; in dev the
 * generator falls back to an in-process scan on every request.
 *
 * The scan paths are intentionally narrow — controllers, DTOs, and the
 * dedicated {@see OpenApiInfo} root — so unrelated code (Models, Repos)
 * never accidentally pollutes the spec.
 */
final class OpenApiGenerator
{
    /**
     * @param list<string> $scanPaths absolute directories to scan
     */
    public function __construct(
        private readonly array $scanPaths,
        private readonly ?string $cacheFile = null,
    ) {
    }

    /**
     * Build a generator that targets the standard Antarctic layout under
     * the given source root (typically `ROOT_PATH` from Bootstrap).
     */
    public static function forSource(string $sourceRoot, ?string $cacheFile = null): self
    {
        $candidates = [
            $sourceRoot . '/Framework/OpenApi',
            $sourceRoot . '/Framework/Controllers',
            $sourceRoot . '/Application/Controllers',
            $sourceRoot . '/Application/Dto',
        ];
        $paths = array_values(array_filter($candidates, static fn (string $p): bool => is_dir($p)));
        return new self($paths, $cacheFile);
    }

    /**
     * @return string OpenAPI document as JSON
     */
    public function toJson(): string
    {
        if ($this->cacheFile !== null && is_file($this->cacheFile)) {
            $contents = file_get_contents($this->cacheFile);
            if (is_string($contents) && $contents !== '') {
                return $contents;
            }
        }
        return $this->scan();
    }

    /**
     * Force a fresh scan (no cache read). Used by `openapi:dump`.
     */
    public function scan(): string
    {
        if ($this->scanPaths === []) {
            return '{"openapi":"3.0.0"}';
        }
        $openapi = Generator::scan($this->scanPaths);
        return $openapi->toJson();
    }

    /**
     * @return list<string>
     */
    public function scanPaths(): array
    {
        return $this->scanPaths;
    }

    public function cacheFile(): ?string
    {
        return $this->cacheFile;
    }
}
