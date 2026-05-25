<?php
declare(strict_types=1);

use Framework\Auth\AuthMiddleware;
use Framework\Auth\JwtConfigFactory;
use Framework\Auth\RefreshTokenRepository;
use Framework\Auth\SystemClock;
use Framework\Auth\TokenService;
use Framework\Config;
use Framework\Container;
use Framework\Dal;
use Framework\Dispatcher;
use Framework\Dotenv;
use Framework\Http\CorsMiddleware;
use Framework\Http\ErrorHandlerMiddleware;
use Framework\Http\LegacyDispatcherMiddleware;
use Framework\Http\MiddlewarePipeline;
use Framework\Http\NotFoundHandler;
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

$container = new Container();
$router = new StandardRouterImpl();
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
