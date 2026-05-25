<?php

declare(strict_types=1);

namespace Framework\Logging;

/**
 * Process-wide holder for the current request's trace ID. The
 * {@see \Framework\Http\TraceIdMiddleware} writes here as the first step of
 * the pipeline; the {@see TraceIdProcessor} reads it when Monolog formats a
 * record so every log line carries the same correlation ID.
 *
 * In a long-running worker (RoadRunner, ReactPHP) callers must reset between
 * requests — the middleware does so on every invocation.
 */
final class TraceIdHolder
{
    private static ?string $id = null;

    public static function set(string $id): void
    {
        self::$id = $id;
    }

    public static function get(): ?string
    {
        return self::$id;
    }

    public static function clear(): void
    {
        self::$id = null;
    }
}
