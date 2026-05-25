<?php

declare(strict_types=1);

namespace Tests\Framework\Console;

use Framework\Console\OpenApiDumpCommand;
use Framework\OpenApi\OpenApiGenerator;
use PHPUnit\Framework\TestCase;

final class OpenApiDumpCommandTest extends TestCase
{
    private string $cacheFile;

    protected function setUp(): void
    {
        $this->cacheFile = sys_get_temp_dir() . '/antarctic-openapi-cmd-' . bin2hex(random_bytes(4)) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
    }

    public function testDumpWritesScannedSpec(): void
    {
        $generator = new OpenApiGenerator([
            dirname(__DIR__, 3) . '/Framework/OpenApi',
            dirname(__DIR__, 3) . '/Framework/Controllers',
            dirname(__DIR__, 3) . '/Application/Dto',
        ]);
        $command = new OpenApiDumpCommand($generator, $this->cacheFile);

        ob_start();
        $code = $command->run([]);
        $stdout = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->cacheFile);
        $this->assertStringContainsString('Wrote', $stdout);

        $doc = json_decode((string) file_get_contents($this->cacheFile), true);
        $this->assertIsArray($doc);
        $this->assertSame('Antarctic API', $doc['info']['title']);
    }

    public function testClearRemovesFile(): void
    {
        file_put_contents($this->cacheFile, '{"openapi":"3.0.0"}');
        $command = new OpenApiDumpCommand(new OpenApiGenerator([]), $this->cacheFile);

        ob_start();
        $code = $command->run(['--clear']);
        $stdout = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileDoesNotExist($this->cacheFile);
        $this->assertStringContainsString('Cleared', $stdout);
    }

    public function testNameAndDescription(): void
    {
        $command = new OpenApiDumpCommand(new OpenApiGenerator([]), $this->cacheFile);
        $this->assertSame('openapi:dump', $command->name());
        $this->assertNotSame('', $command->description());
    }
}
