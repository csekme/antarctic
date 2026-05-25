<?php

declare(strict_types=1);

namespace Framework\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that stamps every record with the current request's
 * trace ID under `extra.trace_id`. With Monolog's {@see \Monolog\Formatter\JsonFormatter}
 * the field surfaces as `extra.trace_id` in each emitted JSON line, letting
 * log aggregators (Loki/ELK) correlate entries belonging to the same request.
 */
final class TraceIdProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $traceId = TraceIdHolder::get();
        if ($traceId === null) {
            return $record;
        }

        $extra = $record->extra;
        $extra['trace_id'] = $traceId;

        return $record->with(extra: $extra);
    }
}
