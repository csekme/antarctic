<?php

declare(strict_types=1);

namespace Tests\Framework;

use DI\FactoryInterface;
use Framework\ContainerFactory;
use Framework\Response;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * A `ContainerFactory::build()` minimális szerződését ellenőrzi:
 *  - PSR-11 `ContainerInterface`-t ad vissza
 *  - autowire-os: típus-hint alapján képes class-okat példányosítani
 *  - php-di `FactoryInterface`-t implementál (M3.d, `make()` per-call)
 *  - `make()` minden hívásra friss példányt ad
 */
final class ContainerFactoryTest extends TestCase
{
    public function testReturnsPsrContainer(): void
    {
        $container = ContainerFactory::build();
        $this->assertInstanceOf(ContainerInterface::class, $container);
    }

    public function testImplementsPhpDiFactoryInterface(): void
    {
        $container = ContainerFactory::build();
        $this->assertInstanceOf(FactoryInterface::class, $container);
    }

    public function testResolvesResponseViaAutowiring(): void
    {
        $container = ContainerFactory::build();
        $response = $container->get(Response::class);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testMakeReturnsFreshResponseEveryCall(): void
    {
        $container = ContainerFactory::build();
        $this->assertInstanceOf(FactoryInterface::class, $container);

        $first = $container->make(Response::class);
        $second = $container->make(Response::class);

        $this->assertInstanceOf(Response::class, $first);
        $this->assertInstanceOf(Response::class, $second);
        $this->assertNotSame($first, $second);
    }

    public function testMakeAcceptsConstructorParameterOverrides(): void
    {
        $container = ContainerFactory::build();
        $this->assertInstanceOf(FactoryInterface::class, $container);

        $instance = $container->make(NeedsScalar::class, ['greeting' => 'hi']);

        $this->assertSame('hi', $instance->greeting);
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

final class NeedsScalar
{
    public function __construct(public readonly string $greeting)
    {
    }
}
