<?php

declare(strict_types=1);

namespace Tests\Framework\Logging;

use Framework\Logging\TraceIdHolder;
use Framework\Logging\TraceIdProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class TraceIdProcessorTest extends TestCase
{
    protected function tearDown(): void
    {
        TraceIdHolder::clear();
    }

    public function testStampsTraceIdOnExtra(): void
    {
        TraceIdHolder::set('abc123');
        $processor = new TraceIdProcessor();

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'hello',
            context: [],
            extra: [],
        );

        $processed = $processor($record);

        $this->assertSame('abc123', $processed->extra['trace_id']);
    }

    public function testLeavesRecordAloneWhenHolderEmpty(): void
    {
        TraceIdHolder::clear();
        $processor = new TraceIdProcessor();

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'hello',
            context: [],
            extra: ['existing' => 'value'],
        );

        $processed = $processor($record);

        $this->assertArrayNotHasKey('trace_id', $processed->extra);
        $this->assertSame('value', $processed->extra['existing']);
    }

    public function testPreservesExistingExtraKeys(): void
    {
        TraceIdHolder::set('xyz');
        $processor = new TraceIdProcessor();

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'hello',
            context: [],
            extra: ['memory_peak_usage' => '1MB'],
        );

        $processed = $processor($record);

        $this->assertSame('xyz', $processed->extra['trace_id']);
        $this->assertSame('1MB', $processed->extra['memory_peak_usage']);
    }
}
