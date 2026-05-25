<?php

declare(strict_types=1);

namespace Tests\Framework;

use Framework\Container;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function testResolvesClassWithoutConstructor(): void
    {
        $container = new Container();

        $instance = $container->get(NoCtor::class);

        $this->assertInstanceOf(NoCtor::class, $instance);
    }

    public function testReturnsValueFromExplicitFactory(): void
    {
        $container = new Container();
        $container->set(NoCtor::class, fn () => new NoCtor());

        $this->assertInstanceOf(NoCtor::class, $container->get(NoCtor::class));
    }

    public function testAutowiresNamedDependencies(): void
    {
        $container = new Container();

        $instance = $container->get(NeedsNoCtor::class);

        $this->assertInstanceOf(NeedsNoCtor::class, $instance);
        $this->assertInstanceOf(NoCtor::class, $instance->dep);
    }

    public function testRejectsConstructorParamWithoutTypeDeclaration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Container())->get(UntypedCtor::class);
    }

    public function testRejectsBuiltInTypedConstructorParam(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Container())->get(BuiltInTypedCtor::class);
    }
}

final class NoCtor
{
}

final class NeedsNoCtor
{
    public function __construct(public NoCtor $dep)
    {
    }
}

final class UntypedCtor
{
    public function __construct($x)
    {
    }
}

final class BuiltInTypedCtor
{
    public function __construct(string $x)
    {
    }
}
