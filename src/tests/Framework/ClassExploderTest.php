<?php

declare(strict_types=1);

namespace Tests\Framework;

use Framework\ClassExploder;
use PHPUnit\Framework\TestCase;

/**
 * A reflection-alapú scan a Composer autoloaderre épít. A teszt egy
 * létező controller-namespace-t scannel — `Framework\Controllers\Api\V1`
 * alatt egyetlen valódi controller él (`AuthController`), így a map-nek
 * pontosan azt kell felismernie.
 */
final class ClassExploderTest extends TestCase
{
    public function testReflectionScanFindsAuthController(): void
    {
        $exploder = new ClassExploder(['Framework\\Controllers\\Api\\V1']);
        $map = $exploder->get_controller_mapping();

        // Az AuthController class-szintű #[Path]-szal NEM rendelkezik, csak
        // method-szintűekkel; emiatt a ClassExploder a `/__class__/{FQCN}`
        // sentinel-kulcsra teszi (üres prefix → a method-szintű path-ok adják
        // a teljes route-ot).
        $expectedKey = '/__class__/Framework\\Controllers\\Api\\V1\\AuthController';
        $this->assertArrayHasKey($expectedKey, $map);
        $this->assertSame('AuthController', $map[$expectedKey]['className']);
        $this->assertSame('Framework\\Controllers\\Api\\V1', $map[$expectedKey]['nameSpace']);
    }

    public function testEmptyNamespaceProducesEmptyMap(): void
    {
        $exploder = new ClassExploder(['Nonexistent\\Namespace']);
        $this->assertSame([], $exploder->get_controller_mapping());
    }
}
