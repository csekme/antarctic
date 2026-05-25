<?php

declare(strict_types=1);

namespace Tests\Framework;

use Framework\SpaMode;
use PHPUnit\Framework\TestCase;

final class SpaModeTest extends TestCase
{
    public function testFromEnvDefaultsToSeparateWhenMissing(): void
    {
        $this->assertSame(SpaMode::SEPARATE, SpaMode::fromEnv(null));
        $this->assertSame(SpaMode::SEPARATE, SpaMode::fromEnv(''));
        $this->assertSame(SpaMode::SEPARATE, SpaMode::fromEnv('garbage'));
    }

    public function testFromEnvRecognisesKnownModes(): void
    {
        $this->assertSame(SpaMode::EMBEDDED, SpaMode::fromEnv('embedded'));
        $this->assertSame(SpaMode::BOTH, SpaMode::fromEnv('both'));
        $this->assertSame(SpaMode::SEPARATE, SpaMode::fromEnv('separate'));
    }

    public function testFromEnvIsCaseInsensitiveAndTrims(): void
    {
        $this->assertSame(SpaMode::EMBEDDED, SpaMode::fromEnv('  EMBEDDED  '));
        $this->assertSame(SpaMode::BOTH, SpaMode::fromEnv('Both'));
    }

    public function testServesSpaIsFalseOnlyForSeparate(): void
    {
        $this->assertFalse(SpaMode::SEPARATE->servesSpa());
        $this->assertTrue(SpaMode::EMBEDDED->servesSpa());
        $this->assertTrue(SpaMode::BOTH->servesSpa());
    }

    public function testRequiresCorsIsFalseOnlyForEmbedded(): void
    {
        $this->assertTrue(SpaMode::SEPARATE->requiresCors());
        $this->assertFalse(SpaMode::EMBEDDED->requiresCors());
        $this->assertTrue(SpaMode::BOTH->requiresCors());
    }

    public function testCurrentReadsAppSpaModeEnv(): void
    {
        $original = getenv('APP_SPA_MODE');
        try {
            putenv('APP_SPA_MODE=embedded');
            $this->assertSame(SpaMode::EMBEDDED, SpaMode::current());

            putenv('APP_SPA_MODE=both');
            $this->assertSame(SpaMode::BOTH, SpaMode::current());

            putenv('APP_SPA_MODE');
            unset($_ENV['APP_SPA_MODE'], $_SERVER['APP_SPA_MODE']);
            $this->assertSame(SpaMode::SEPARATE, SpaMode::current());
        } finally {
            if ($original === false) {
                putenv('APP_SPA_MODE');
            } else {
                putenv('APP_SPA_MODE=' . $original);
            }
        }
    }
}
