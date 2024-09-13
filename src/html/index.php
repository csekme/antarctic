<?php
declare(strict_types=1);
use \Framework\Request as Request;

define("ROOT_PATH", dirname(__DIR__));

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

$dotenv = new Framework\Dotenv;
$dotenv->load(ROOT_PATH . "/.env");

$container = new \Framework\Container();
$router = new \Framework\Routing\StandardRouterImpl();


// Add the routes
$router->add('', ['controller' => 'FrameworkDashboardController', 'action' => 'index']);
$router->add('test-email', ['controller' => 'FrameworkDashboardController', 'action' => 'testEmail']);
$router->add('password/reset/{token:[\da-f]+}', ['controller' => 'Password', 'action' => 'reset']);
$router->add('signup/activate/{token:[\da-f]+}', ['controller' => 'Signup', 'action' => 'activate']);
$router->add('test', ['controller' => 'TestController', 'action' => 'test']);

// Signup routes
$router->add('signup/new', ['controller' => 'SignupController', 'action' => 'new']);
$router->add('signup/signup', ['controller' => 'SignupController', 'action' => 'signup']);
$router->add('signup/success', ['controller' => 'SignupController', 'action' => 'success']);
$router->add('signup/unsuccessful', ['controller' => 'SignupController', 'action' => 'unsuccessful']);
$router->add('signup/activate/{token:[\da-f]+}', ['controller' => 'SignupController', 'action' => 'activate']);

// Login routes
$router->add('login', ['controller' => 'LoginController', 'action' => 'login']);
$router->add('login/enter', ['controller' => 'LoginController', 'action' => 'enter']);
$router->add('logout', ['controller' => 'LogoutController', 'action' => 'logout']);
$router->add('users', ['controller' => 'UserController', 'action' => 'index']);
$router->add('password/reset/{token:[\da-f]+}', ['controller' => 'Password', 'action' => 'reset']);
$router->add('{controller}/{action}');

$dispatcher = new Framework\Dispatcher($router, $container);

$dispatcher->handleRequest(Request::createFromGlobals());
//$response->send();
