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
use Framework\Http\HttpsRedirectMiddleware;
use Framework\Http\LegacyDispatcherMiddleware;
use Framework\Http\MiddlewarePipeline;
use Framework\Http\NotFoundHandler;
use Framework\Http\RateLimit\InMemoryStore;
use Framework\Http\RateLimit\PhpRedisAdapter;
use Framework\Http\RateLimit\PredisAdapter;
use Framework\Http\RateLimit\RateLimitConfig;
use Framework\Http\RateLimit\RateLimitMiddleware;
use Framework\Http\RateLimit\RateLimitStore;
use Framework\Http\RateLimit\RedisLike;
use Framework\Http\RateLimit\RedisStore;
use Framework\Http\SecurityHeadersMiddleware;
use Framework\Http\TraceIdMiddleware;
use Framework\Logging\LoggerFactory;
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
$securityHeadersConfig = require ROOT_PATH . '/config/security-headers.php';
$debug = Config::show_errors();
$logger = LoggerFactory::fromEnv();

// Pipeline order:
//   SecurityHeaders — outermost, decorates every response (including 5xx).
//   TraceId         — sets the per-request correlation id BEFORE ErrorHandler
//                     so logged exceptions carry trace_id in their extra.
//   ErrorHandler    — catches downstream Throwables, logs 5xx, returns JSON/HTML.
//   Cors / RateLimit / Auth / Dispatcher follow.
/** @var list<MiddlewareInterface> $middlewares */
$middlewares = [
    new SecurityHeadersMiddleware($securityHeadersConfig),
    new TraceIdMiddleware(),
    new ErrorHandlerMiddleware(debug: $debug, logger: $logger),
];

// Opcionális HTTPS-redirect (`APP_FORCE_HTTPS=1`). TLS-terminating proxy
// mögött `APP_TRUST_PROXY=1` is kell, különben loopol. A healthcheck
// endpoint-okat (`/api/v1/healthz`, `/api/v1/readyz`) kihagyjuk, hogy a
// k8s probe pod-IP-n is HTTP-vel pingeljen.
if (filter_var(getenv('APP_FORCE_HTTPS') ?: '0', FILTER_VALIDATE_BOOL)) {
    $middlewares[] = new HttpsRedirectMiddleware(
        trustProxy: filter_var(getenv('APP_TRUST_PROXY') ?: '0', FILTER_VALIDATE_BOOL),
        excludedPrefixes: ['/api/v1/healthz', '/api/v1/readyz'],
    );
}

$middlewares[] = new CorsMiddleware($corsConfig);

// Rate limit a Cors után — preflight OPTIONS-ok ne számítsanak bele a bucketbe.
// A backend a config-ból jön: `memory` (dev/single-worker) vagy `redis`
// (production multi-worker FPM). Master switch a config 'enabled' flag-je.
$rateLimitConfig = require ROOT_PATH . '/config/rate-limit.php';
if (RateLimitConfig::isEnabled($rateLimitConfig)) {
    /**
     * Build a {@see RedisLike} client from the configured DSN. Kept inline so
     * the imports stay close to their single call site and tests can sub the
     * full store via {@see RateLimitMiddleware} constructor injection.
     */
    $buildRedisLike = static function (string $backend, string $dsn): RedisLike {
        if ($backend === 'phpredis') {
            if (!class_exists('Redis')) {
                throw new RuntimeException(
                    'APP_RATE_LIMIT_BACKEND=phpredis requires the ext-redis extension to be installed.',
                );
            }
            $parsed = parse_url($dsn) ?: [];
            $host = (string) ($parsed['host'] ?? '127.0.0.1');
            $port = (int) ($parsed['port'] ?? 6379);
            /** @var \Redis $client */
            $client = new \Redis();
            $client->connect($host, $port);
            if (isset($parsed['pass']) && $parsed['pass'] !== '') {
                $client->auth((string) $parsed['pass']);
            }
            return new PhpRedisAdapter($client);
        }
        return new PredisAdapter(new Predis\Client($dsn));
    };

    /** @var RateLimitStore $rateLimitStore */
    $rateLimitStore = match (strtolower((string) ($rateLimitConfig['backend'] ?? 'memory'))) {
        'redis', 'predis' => new RedisStore(
            $buildRedisLike('predis', (string) $rateLimitConfig['redis_dsn']),
            (string) $rateLimitConfig['redis_prefix'],
        ),
        'phpredis' => new RedisStore(
            $buildRedisLike('phpredis', (string) $rateLimitConfig['redis_dsn']),
            (string) $rateLimitConfig['redis_prefix'],
        ),
        default => new InMemoryStore(),
    };

    $middlewares[] = new RateLimitMiddleware(
        rules: RateLimitConfig::rulesFromArray($rateLimitConfig),
        store: $rateLimitStore,
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
