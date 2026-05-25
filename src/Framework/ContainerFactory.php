<?php

declare(strict_types=1);

namespace Framework;

use DI\Container as PhpDiContainer;
use DI\ContainerBuilder;
use PDO;
use Psr\Container\ContainerInterface;

use function DI\factory;

/**
 * A `php-di/php-di` (PSR-11 kompatibilis) container felépítő-helye.
 *
 * Korábban a saját `Framework\Container` szolgált autowire-DI-ként; az M3.c
 * óta a `php-di/php-di` szállítja. Autowire bekapcsolva — minden konstruktor-
 * argumentumot a típusa alapján képes feloldani. Az M3.d óta az attribute-DI
 * is engedélyezett (`#[Inject]`, `#[Injectable]`), és a {@see Dispatcher}
 * `make()`-en keresztül kéri a controller-eket, így a route-paraméterek és
 * a DI-deps együtt oldódnak fel.
 *
 * Production-ban opcionálisan compile-cache (`enableCompilation`) — gyorsabb
 * cold-start, de írható `var/cache/di/` mappát igényel. A bekapcsolás
 * `APP_DI_COMPILE=1` env változóval történik (lásd `Bootstrap.php`).
 *
 * NB. A `Framework\Response`-t a {@see Dispatcher} mindig `make()`-kel kéri,
 * így minden request friss példányt kap (long-running worker safe).
 */
final class ContainerFactory
{
    public static function build(?string $compilationDir = null): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(true);

        $builder->addDefinitions([
            // A live PDO ugyanaz a `Framework\Dal` által hagyományosan használt
            // singleton-conn; a `Dal::getConnection()` ezt képesen factory-vá teszi.
            // PSR-11 autowire-rel a `UserRepository` és `TwoFactorRepository`
            // konstruktora automatikusan megkapja.
            PDO::class => factory(static fn (): PDO => Dal::getConnection()),
        ]);

        if ($compilationDir !== null) {
            $builder->enableCompilation($compilationDir);
        }

        /** @var PhpDiContainer $container */
        $container = $builder->build();
        return $container;
    }
}
