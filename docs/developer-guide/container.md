# Container és Dependency Injection

Az Antarctic a [`php-di/php-di`](https://php-di.org/) (PSR-11 kompatibilis) container-t használja a service-ek életciklusának kezelésére. A keretrendszer csak egy vékony adapterrel (`Framework\ContainerFactory`) áll fölötte — a `php-di` minden képessége (autowire, attribute-DI, compile-cache) elérhető.

!!! info "Aktuális állapot (M3.c)"
    A container ma **belső infrastruktúra**: a `Dispatcher` használja a `Response` instance kezelésére. **Controller-szintű constructor-injection még nem aktív** (egy követő PR-ben jön — addig a Dispatcher direkt `new $controller($params)`-szel példányosít). Amint a controller-injection landol, ez a fejezet részletes példákkal bővül.

## A container felépítése

```php
use Framework\ContainerFactory;
use Psr\Container\ContainerInterface;

// Dev / standard:
$container = ContainerFactory::build();

// Production compile-cache-szel:
$container = ContainerFactory::build(__DIR__ . '/var/cache/di');
```

A `Bootstrap.php` ezt automatikusan végzi; `APP_DI_COMPILE=1` env változóval kapcsolható a compile-cache:

```bash
APP_DI_COMPILE=1 php -S localhost:8080 -t src/html
```

## Autowire (alapértelmezetten be)

A `php-di` típus-alapú constructor-paraméter feloldást ad. Tipikus service-osztály:

```php
namespace Application\Services;

use Framework\Auth\TokenService;

final class SessionLister
{
    public function __construct(private readonly TokenService $tokens) {}
}
```

Ha a `TokenService` is feloldható a containerből (singleton-szerűen), a `$container->get(SessionLister::class)` automatikusan `new SessionLister($tokenService)`-t hív.

!!! tip "Mit jelent, hogy 'feloldható'"
    Ha egy osztály konstruktorának minden paramétere típus-hintezett és nem-primitív (vagy a containerben explicit definícióval szerepel), a `php-di` autowire-elni tudja.

## Service definíciók

A `ContainerFactory::build()` jelenleg csak autowire-t használ — nincs explicit definíció. Ha egy szolgáltatást konfigurálni szeretnél (pl. szingleton URL, vagy nem-default impl-választás), az `addDefinitions()` lépést bővíteni kell:

```php
// src/Framework/ContainerFactory.php
$builder->addDefinitions([
    'mailer.dsn' => DI\env('MAILER_DSN', 'sendmail://default'),
    \Application\Services\Mailer::class => DI\autowire()
        ->constructorParameter('dsn', DI\get('mailer.dsn')),
]);
```

## Compile-cache

Production-időben a `php-di` képes előre lefordítani a container-graph-ot egy generált PHP-fájllá, ami `opcache`-elhető. Ez gyorsabb cold-start-ot ad (~10-100x), de **írható mappát** igényel.

```bash
# Deploy lépés:
composer install --no-dev --optimize-autoloader
APP_DI_COMPILE=1 php -r "require 'vendor/autoload.php'; Framework\ContainerFactory::build('var/cache/di');"
```

A `var/cache/di/` mappa `gitignore`-olt (`/src/var/`).

## Tesztelés

A `Psr\Container\ContainerInterface` PSR-11 interface — minden teszt mockolhatja vagy direkten példányosíthatja:

```php
use Psr\Container\ContainerInterface;

$mock = $this->createMock(ContainerInterface::class);
$mock->method('get')->with(Response::class)->willReturn(new Response());

$dispatcher = new Dispatcher($router, $mock);
```

A `Framework\ContainerFactory::build()` minden hívásra friss, üres-cache-es `DI\Container`-t ad.

## Controller-injection (M3.d)

A `Dispatcher` a controllereket a container `make()`-en keresztül kéri, ezért a konstruktor-injektált függőségek autowire-rel feloldódnak, miközben a meglévő `array $route_params` argumentum is megmarad:

```php
class TodoController extends Controller
{
    public function __construct(
        public readonly TodoRepository $todos,    // autowired
        public readonly ClockInterface $clock,    // autowired
        array $route_params = [],                 // route match params
    ) {
        parent::__construct($route_params);
    }
}
```

A `make()` paraméternév-alapú override-okat fogad, és a Dispatcher mind a `route_params`, mind a legacy `params` kulcsot átadja — így a régi és új signature is működik.

A `Response` minden dispatchre friss példányt kap (`$container->make(Response::class)`), így long-running worker setupokban (RoadRunner, ReactPHP) sem szivárog header/body két request között.

## Mit *nem* tartalmaz a jelenlegi container

- **Attribute-DI a controller property-szinten** — az `useAttributes(true)` engedélyezve van (`#[Inject]` property-szinten feloldódna), de a `Controller` base még a `setResponse()` szetterrel kapja a Response-t. Egy követő iteráció lecserélheti property-DI-re.
- **Lazy services** — még nem aktív; ha egy service drága construct-ja megéri, a `DI\autowire()->lazy()` opciót lehet majd használni.

## Lásd még

- [php-di hivatalos doksi](https://php-di.org/doc/)
- [PSR-11 Container Interface](https://www.php-fig.org/psr/psr-11/)
- [Configuration](configuration.md) — env változók (köztük `APP_DI_COMPILE`)
