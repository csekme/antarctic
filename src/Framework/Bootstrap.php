<?php
declare(strict_types=1);

use Framework\Auth\AuthMiddleware;
use Framework\Auth\JwtConfigFactory;
use Framework\Auth\RefreshTokenRepository;
use Framework\Auth\SystemClock;
use Framework\Auth\TokenService;
use Framework\Config;
use Framework\ContainerFactory;
use Framework\Dal;
use Framework\Dispatcher;
use Framework\Dotenv;
use Framework\Http\CorsMiddleware;
use Framework\Http\ErrorHandlerMiddleware;
use Framework\Http\LegacyDispatcherMiddleware;
use Framework\Http\MiddlewarePipeline;
use Framework\Http\NotFoundHandler;
use Framework\Http\RateLimit\InMemoryStore;
use Framework\Http\RateLimit\RateLimitConfig;
use Framework\Http\RateLimit\RateLimitMiddleware;
use Framework\Routing\RouteCache;
use Framework\Routing\StandardRouterImpl;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Server\MiddlewareInterface;

error_reporting(E_ALL);
set_error_handler('Framework\ErrorHandler::errorHandler');

session_start();

$dotenv = new Dotenv();
$dotenv->load(ROOT_PATH . "/.env");

// PSR-11 container (php-di). Production-ban opt-in compilation env-flag mögött.
$compilationDir = getenv('APP_DI_COMPILE') ? ROOT_PATH . '/var/cache/di' : null;
$container = ContainerFactory::build($compilationDir);

// Route cache: ha létezik a `var/cache/routes.php`, abból töltjük a route-táblát,
// különben reflection-scan minden requesten (dev).
$routeCache = new RouteCache(ROOT_PATH . '/var/cache/routes.php');
$cachedRoutes = $routeCache->load();
$router = new StandardRouterImpl($cachedRoutes);
$dispatcher = new Dispatcher($router, $container);

$psr17 = new Psr17Factory();
$request = (new ServerRequestCreator($psr17, $psr17, $psr17, $psr17))->fromGlobals();

$corsConfig = require ROOT_PATH . '/config/cors.php';
$debug = Config::show_errors();

/** @var list<MiddlewareInterface> $middlewares */
$middlewares = [
    new ErrorHandlerMiddleware(debug: $debug),
    new CorsMiddleware($corsConfig),
];

// Rate limit a Cors után — preflight OPTIONS-ok ne számítsanak bele a bucketbe.
// In-memory store dev/single-worker SAPI-hoz; multi-worker FPM-hez Redis-adapter
// kell (M5 prod deploy). A master switch a config 'enabled' flag-je.
$rateLimitConfig = require ROOT_PATH . '/config/rate-limit.php';
if (RateLimitConfig::isEnabled($rateLimitConfig)) {
    $middlewares[] = new RateLimitMiddleware(
        rules: RateLimitConfig::rulesFromArray($rateLimitConfig),
        store: new InMemoryStore(),
        clock: new SystemClock(),
        trustProxy: RateLimitConfig::trustProxy($rateLimitConfig),
    );
}

// AuthMiddleware csak akkor regisztrálódik, ha a JWT konfiguráció érvényes.
// Hiányzó kulcs (pl. még nem futott a `bin/console keys:generate`) esetén
// a pipeline auth nélkül megy, és a #[RequireAuth] kontrollerek 401-et dobnak.
try {
    $jwtConfig = require ROOT_PATH . '/config/jwt.php';
    $tokenService = new TokenService(
        jwt: JwtConfigFactory::create($jwtConfig),
        refreshTokens: new RefreshTokenRepository(Dal::getConnection()),
        clock: new SystemClock(),
        issuer: $jwtConfig['issuer'],
        audience: $jwtConfig['audience'],
        accessTtl: (int) $jwtConfig['access_ttl'],
        refreshTtl: (int) $jwtConfig['refresh_ttl'],
        clockSkew: (int) $jwtConfig['clock_skew'],
    );
    $middlewares[] = new AuthMiddleware($tokenService);
} catch (\Throwable $e) {
    error_log('[Antarctic] AuthMiddleware not registered: ' . $e->getMessage());
}

$middlewares[] = new LegacyDispatcherMiddleware($dispatcher);

$pipeline = new MiddlewarePipeline($middlewares, new NotFoundHandler());

(new SapiEmitter())->emit($pipeline->handle($request));
