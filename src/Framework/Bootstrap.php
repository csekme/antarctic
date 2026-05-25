<?php
declare(strict_types=1);

use Framework\Config;
use Framework\Container;
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

$pipeline = new MiddlewarePipeline(
    [
        new ErrorHandlerMiddleware(debug: $debug),
        new CorsMiddleware($corsConfig),
        new LegacyDispatcherMiddleware($dispatcher),
    ],
    new NotFoundHandler(),
);

(new SapiEmitter())->emit($pipeline->handle($request));
