<?php
declare(strict_types=1);
use \Framework\Request as Request;

error_reporting(E_ALL);
set_error_handler('Framework\ErrorHandler::errorHandler');
set_exception_handler('Framework\ErrorHandler::exceptionHandlerHtml');

session_start();

$dotenv = new Framework\Dotenv;
$dotenv->load(ROOT_PATH . "/.env");

$container = new \Framework\Container();
$router = new \Framework\Routing\StandardRouterImpl();

$dispatcher = new Framework\Dispatcher($router, $container);
$dispatcher->handleRequest(Request::createFromGlobals());

