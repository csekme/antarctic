<?php
//declare(strict_types=1);
use \Framework\Request as Request;
/**
 * Composer
 */

$classLoader = require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Error and Exception handling
 */
error_reporting(E_ALL);
set_error_handler('Framework\ErrorHandler::errorHandler');
set_exception_handler('Framework\ErrorHandler::exceptionHandlerHtml');

/**
 * Sessions
 */
session_start();


$container = new \Framework\Container();
$router = new \Framework\Routing\StandardRouterImpl();


// Add the routes
$router->add('', ['controller' => 'FrameworkDashboardController', 'action' => 'index']);
$router->add('password/reset/{token:[\da-f]+}', ['controller' => 'Password', 'action' => 'reset']);
$router->add('signup/activate/{token:[\da-f]+}', ['controller' => 'Signup', 'action' => 'activate']);
$router->add('test', ['controller' => 'TestController', 'action' => 'test']);

$router->add('{controller}/{action}');


$dispatcher = new Framework\Dispatcher($router, $container);

$dispatcher->handleRequest(Request::createFromGlobals());
//$response->send();
