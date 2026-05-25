<?php

declare(strict_types=1);

namespace Framework\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

/**
 * Builds the application logger: structured JSON on `php://stdout`, with the
 * current request's trace ID stamped on every record. Channel name and level
 * are env-driven (`APP_LOG_CHANNEL`, `APP_LOG_LEVEL`).
 *
 * 12-factor compliant: logs go to stdout; the container runtime / aggregator
 * is responsible for routing.
 */
final class LoggerFactory
{
    public static function create(
        string $channel = 'app',
        Level $level = Level::Info,
        string $stream = 'php://stdout',
    ): LoggerInterface {
        $handler = new StreamHandler($stream, $level);
        $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, true));

        $logger = new Logger($channel);
        $logger->pushHandler($handler);
        $logger->pushProcessor(new PsrLogMessageProcessor());
        $logger->pushProcessor(new TraceIdProcessor());

        return $logger;
    }

    public static function fromEnv(): LoggerInterface
    {
        $channel = getenv('APP_LOG_CHANNEL') ?: 'app';
        $levelName = strtoupper((string) (getenv('APP_LOG_LEVEL') ?: 'INFO'));
        $level = match ($levelName) {
            'DEBUG' => Level::Debug,
            'NOTICE' => Level::Notice,
            'WARNING' => Level::Warning,
            'ERROR' => Level::Error,
            'CRITICAL' => Level::Critical,
            'ALERT' => Level::Alert,
            'EMERGENCY' => Level::Emergency,
            default => Level::Info,
        };
        return self::create($channel, $level);
    }
}
