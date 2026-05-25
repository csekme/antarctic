<?php

declare(strict_types=1);

namespace Tests\Framework\Logging;

use Framework\Logging\LoggerFactory;
use Framework\Logging\TraceIdHolder;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerFactoryTest extends TestCase
{
    private string $tmpFile = '';

    protected function setUp(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'logger-factory-');
        $this->assertNotFalse($tmp);
        $this->tmpFile = $tmp;
    }

    protected function tearDown(): void
    {
        TraceIdHolder::clear();
        if ($this->tmpFile !== '' && file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testEmitsJsonWithTraceIdInExtra(): void
    {
        TraceIdHolder::set('trace-abc');
        $logger = LoggerFactory::create('test', Level::Debug, $this->tmpFile);
        $this->assertInstanceOf(Logger::class, $logger);

        $logger->info('hello world', ['user_id' => 42]);

        $content = (string) file_get_contents($this->tmpFile);
        $lines = array_values(array_filter(explode("\n", trim($content))));
        $this->assertCount(1, $lines);

        $decoded = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('test', $decoded['channel']);
        $this->assertSame('hello world', $decoded['message']);
        $this->assertSame('INFO', $decoded['level_name']);
        $this->assertSame(42, $decoded['context']['user_id']);
        $this->assertSame('trace-abc', $decoded['extra']['trace_id']);
    }

    public function testRespectsConfiguredLevel(): void
    {
        $logger = LoggerFactory::create('test', Level::Warning, $this->tmpFile);

        $logger->info('ignored');
        $logger->warning('kept');

        $content = (string) file_get_contents($this->tmpFile);
        $this->assertStringNotContainsString('ignored', $content);
        $this->assertStringContainsString('kept', $content);
    }

    public function testFromEnvHonoursLogLevel(): void
    {
        putenv('APP_LOG_LEVEL=DEBUG');
        putenv('APP_LOG_CHANNEL=antarctic');
        try {
            $logger = LoggerFactory::fromEnv();
            $this->assertInstanceOf(Logger::class, $logger);
            $this->assertSame('antarctic', $logger->getName());
        } finally {
            putenv('APP_LOG_LEVEL');
            putenv('APP_LOG_CHANNEL');
        }
    }
}
