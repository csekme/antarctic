<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\AbstractController;
use Framework\ClassExploder;
use Framework\Path;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * Az alap-router: az `Application` és `Framework` controller-namespace-ekből
 * összegyűjtött `#[Path]` attribútumokat regexes routing-táblába konvertálja.
 *
 * Két konstruktor-módja van:
 *
 *   - **Discovery**: paraméter nélkül scanneli a `ClassExploder`-rel a
 *     controller-osztályokat és reflection-nel olvassa a `#[Path]`-eket.
 *     Dev-időben minden requesten lefut.
 *
 *   - **Cache hydrate**: a `$cachedRoutes` paraméterrel azonnal a kész
 *     route-tábla töltődik be — a `RouteCache::load()` adja vissza.
 *     Production-ban ezt használjuk; nincs reflection-cost.
 */
class StandardRouterImpl implements Router
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $routes = [];

    /**
     * @param array<string, array<string, mixed>>|null $cachedRoutes
     *    Ha nem null, ezzel inicializáljuk a route-táblát és NEM scanneljük újra
     *    a controller-osztályokat.
     *
     * @throws ReflectionException
     */
    public function __construct(?array $cachedRoutes = null)
    {
        if ($cachedRoutes !== null) {
            $this->routes = $cachedRoutes;
            return;
        }

        $this->routes = self::discoverRoutes();
    }

    /**
     * Reflection-alapú scan: a `ClassExploder` által visszaadott controller-listából
     * minden `#[Path]`-os method-re route-ot képez. A visszaadott tömböt a
     * `RouteCache::save()` is használhatja.
     *
     * @return array<string, array<string, mixed>>
     * @throws ReflectionException
     */
    public static function discoverRoutes(): array
    {
        $routes = [];
        $classExploder = new ClassExploder();
        $mapping = $classExploder->get_controller_mapping();
        foreach ($mapping as $path => $param) {
            // A `/__class__/{FQCN}` sentinel kulcs azt jelzi: az osztálynak
            // nincs class-szintű `#[Path]` prefix; csak a method-szintű attribútumok
            // adják a teljes route-ot.
            if (str_starts_with($path, '/__class__/')) {
                $path = '';
            } else {
                $path = ltrim($path, '/');
            }
            $className = $param['className'];
            $namespace = $param['nameSpace'];
            $fullQualifiedClass = $namespace . '\\' . $className;
            if (!class_exists($fullQualifiedClass)) {
                continue;
            }
            $reflectionClass = new ReflectionClass($fullQualifiedClass);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                $action = $method->name;
                $attributes = $method->getAttributes(Path::class);
                foreach ($attributes as $attribute) {
                    $attributeObj = $attribute->newInstance();
                    $pathVariable = $attributeObj->path ?? '';
                    $emptyPath = ($pathVariable === '');
                    if (!$emptyPath) {
                        if ($path !== '' && !str_starts_with($pathVariable, '/')) {
                            $pathVariable = '/' . $pathVariable;
                        } elseif ($path === '' && str_starts_with($pathVariable, '/')) {
                            $pathVariable = substr($pathVariable, 1);
                        }
                    }
                    if (str_contains($pathVariable, '{')) {
                        $pathVariable = self::convertPathPattern($pathVariable);
                    }
                    $routeKey = self::compileRoute($path . $pathVariable);
                    $routes[$routeKey] = [
                        'controller' => $className,
                        'namespace' => $param['nameSpace'],
                        'action' => $pathVariable === '' ? '' : $action,
                        'method' => $attributeObj->method ?? AbstractController::GET,
                        'emptyPath' => $emptyPath,
                    ];
                }
            }
        }
        return $routes;
    }

    /**
     * Az `add()` mostmár tisztán a routing-táblába tesz; a `discoverRoutes()`
     * is ezt használja, ill. a `RouteCacheCommand` ezzel iterál.
     *
     * @param array<string, mixed> $params
     */
    public function add(string $route, array $params = []): void
    {
        $this->routes[self::compileRoute($route)] = $params;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function match(string $url, ?string $method = null): MatchResult
    {
        $url = self::removeQueryStringVariables($url);
        $methodUpper = $method !== null ? strtoupper($method) : null;
        $allowedMethods = [];

        foreach ($this->routes as $routeRegex => $params) {
            if (!preg_match($routeRegex, $url, $matches)) {
                continue;
            }
            $routeMethod = strtoupper((string) ($params['method'] ?? AbstractController::GET));

            // Method-aware leg: ha a metódus nem stimmel, jelöljük allow-list-be.
            if ($methodUpper !== null && $methodUpper !== $routeMethod) {
                $allowedMethods[] = $routeMethod;
                continue;
            }

            foreach ($matches as $key => $matchValue) {
                if (is_string($key)) {
                    $params[$key] = $matchValue;
                }
            }
            return MatchResult::found($params);
        }

        if ($allowedMethods !== []) {
            return MatchResult::methodNotAllowed($allowedMethods);
        }
        return MatchResult::notFound();
    }

    protected static function convertPathPattern(string $path): string
    {
        return (string) preg_replace_callback(
            '/\{(\w+)(?::([^}]+))?\}/',
            static function (array $matches): string {
                $varName = $matches[1];
                $regex = $matches[2] ?? '[^/]+';
                return '(?P<' . $varName . '>' . $regex . ')';
            },
            $path,
        );
    }

    /**
     * Konvertálja a route-mintát end-to-end PCRE regexszé. Ugyanaz a logika
     * fut, mint a régi `add()`-ben — kiterjesztve arra, hogy a `discoverRoutes`
     * is használja static contextben.
     */
    protected static function compileRoute(string $route): string
    {
        $regex = preg_replace('/\//', '\\/', $route);
        $regex = preg_replace('/\{([a-z]+)\}/', '(?P<\1>[a-z-]+)', $regex);
        $regex = preg_replace('/\{([a-z]+):([^\}]+)\}/', '(?P<\1>\2)', $regex);
        return '/^' . $regex . '$/i';
    }

    protected static function removeQueryStringVariables(string $url): string
    {
        if ($url === '') {
            return $url;
        }
        $parts = explode('&', $url, 2);
        return strpos($parts[0], '=') === false ? $parts[0] : '';
    }
}
