<?php

declare(strict_types=1);

namespace Tests\Framework;

use Framework\ContainerFactory;
use Framework\Response;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * A `ContainerFactory::build()` minimális szerződését ellenőrzi:
 *  - PSR-11 `ContainerInterface`-t ad vissza
 *  - autowire-os: típus-hint alapján képes class-okat példányosítani
 *  - explicit `Response` definíciónk minden `get()`-re új példányt ad
 */
final class ContainerFactoryTest extends TestCase
{
    public function testReturnsPsrContainer(): void
    {
        $container = ContainerFactory::build();
        $this->assertInstanceOf(ContainerInterface::class, $container);
    }

    public function testResolvesResponseViaAutowiring(): void
    {
        $container = ContainerFactory::build();
        $response = $container->get(Response::class);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testAutowiresClassWithoutConstructor(): void
    {
        $container = ContainerFactory::build();
        $instance = $container->get(NoConstructorService::class);
        $this->assertInstanceOf(NoConstructorService::class, $instance);
    }

    public function testAutowiresConstructorDependencies(): void
    {
        $container = ContainerFactory::build();
        $instance = $container->get(NeedsDependency::class);
        $this->assertInstanceOf(NeedsDependency::class, $instance);
        $this->assertInstanceOf(NoConstructorService::class, $instance->dep);
    }

    public function testHasReturnsTrueForAutowireableClass(): void
    {
        $container = ContainerFactory::build();
        $this->assertTrue($container->has(Response::class));
    }
}

final class NoConstructorService
{
}

final class NeedsDependency
{
    public function __construct(public readonly NoConstructorService $dep)
    {
    }
}
