<?php

declare(strict_types=1);

namespace Framework;

use Exception;
use Framework\Routing\Router as Router;
use ReflectionAttribute;
use ReflectionClass;

/**
 * Class Dispatcher, handles the incoming request and dispatches it to the appropriate controller
 * @package Framework
 * @version 1.0
 * @since 1.0
 * @author Krisztián Csekme
 * @license GNU GPL v3.0
 * @see Router
 * @see Container
 */
readonly class Dispatcher
{

    public function __construct(private Router $router, private Container $container)
    {
    }


    /**
     * Handle the incoming request and dispatch it to the appropriate controller
     * @param Request $request request
     * @return Response response
     * @throws Exception
     */
    public function handleRequest(Request $request): Response
    {

        $params = $this->router->match($request->uri);

        if ($params === false) {
            throw new Exception(message: "No route matched for '$request->uri' with method '{$request->method}'", code: 404);
        }

        $this->crossSiteRequestForgeryProtection($request);

        $controller = $params['controller'];
        $controller = $this->convertToStudlyCaps($controller);
        $controller = $this->getNamespace($params, "Application\Controllers\\") . $controller;

        if (!class_exists($controller)) {
            $controller = $params['controller'];
            $controller = $this->convertToStudlyCaps($controller);
            $controller = $this->getNamespace($params, "Framework\Controllers\\") . $controller;
        }
        if (class_exists($controller)) {
            $controller_object = new $controller($params);
            $reflectionClass = new ReflectionClass($controller_object::class);
            $attributes = $reflectionClass->getAttributes();
            foreach ($attributes as $attribute) {
                $this->processAnnotation($attribute, $controller_object);
            }
            $controller_object->setRequest($request);
            $controller_object->setResponse($this->container->get(Response::class));
            $action = $params['action'];
            $action = $this->convertToCamelCase($action);

            $request_method = $_SERVER['REQUEST_METHOD'];
            $found = false;
            if ($action == '') { // if no action is specified, try to find the action based on the request method
                $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
                foreach ($methods as $method) {
                    $attributes = $method->getAttributes(Path::class);
                    foreach ($attributes as $attribute) {
                        $attr = $attribute->newInstance();
                        if ($attr->method != null && $attr->path == null) {
                            if ($request_method == $attr->method) {
                                $action = $method->getName();
                                $found = true;
                                break;
                            }
                        }
                    }
                    if ($found) {
                        break;
                    }
                }
            }
            $method = $reflectionClass->getMethod($action);
            if (isset($this->params['method'])) {
                $path_method = $this->params['method'];
                if ($request_method !== $path_method) {
                    header("HTTP/1.1 405 Method not allowed.");

                }
            }

            $attributes = $method->getAttributes();
            foreach ($attributes as $attribute) {
                $this->processAnnotation($attribute, $controller_object);
            }

            if ($request_method !== 'GET') {
                if ($method->getNumberOfParameters() === 1) {
                    $response = $controller_object->$action($_POST);
                } else {
                    $response = $controller_object->$action();
                }
            } else {
                if ($method->getNumberOfParameters() === 1) {
                    $methodParameters = $method->getParameters();
                    $value = $params[$methodParameters[0]->name];
                    $response = $controller_object->$action($value);
                } else {
                    $response = $controller_object->$action();
                }
            }
            $response->send();
        } else {
            throw new Exception("Controller class $controller not found");
        }

        return $response;
    }

    /**
     * Cross-site request forgery protection
     * @param Request $request
     * @throws Exception
     */
    private function crossSiteRequestForgeryProtection(Request $request): void
    {
        if ($request->method == AbstractController::GET) {
            $token = new Token();
            $_SESSION['csrf'] = $token;
        } else {
            if (isset($_SESSION['csrf'])) {
                if (!isset($_POST['_csrf'])) {
                    throw new Exception(message: "Method not allowed", code: 405);
                }
                $token = $_SESSION['csrf'];
                $value = $_POST['_csrf'];
                $check = new Token($value);
                if ($check->getHash() != $token->getHash()) {
                    throw new Exception(message: "Method not allowed", code: 405);
                }
            }
        }
    }

    /**
     * Process the annotations of classes and methods in controllers.
     * @param ReflectionAttribute $attribute
     * @param Controller $controller_object
     * @return void
     * @throws Exception
     */
    private function processAnnotation(ReflectionAttribute $attribute, Controller $controller_object): void
    {
        $attribute = $attribute->newInstance();
        if ($attribute instanceof HasRoles) {
            $requestRoles = $attribute->roles;
            $user = Auth::getUser();

            if ($user == null) {
                throw new Exception("User is not logged in", 401);
            }

            $userRoles = $user->getRoles();
            $allowed = false;
            foreach ($requestRoles as $roleName) {
                if (in_array($roleName, $userRoles)) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                throw new Exception("User does not have the required role", 403);
            }
        }
        if ($attribute instanceof RequireLogin) {
            $controller_object->requireLogin();
        }
    }

    /**
     * Convert the string with hyphens to StudlyCaps,
     * e.g. post-authors => PostAuthors
     *
     * @param string $string The string to convert
     *
     * @return string
     */
    protected function convertToStudlyCaps(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));
    }

    /**
     * Convert the string with hyphens to camelCase,
     * e.g. add-new => addNew
     *
     * @param string $string The string to convert
     *
     * @return string
     */
    protected function convertToCamelCase(string $string): string
    {
        return lcfirst($this->convertToStudlyCaps($string));
    }

    /**
     * Get the namespace for the controller class. The namespace defined in the
     * route parameters is added if present.
     *
     * @return string The request URL
     */
    protected function getNamespace($params, $namespace): string
    {
        if (array_key_exists('namespace', $params)) {
            $namespace = $params['namespace'] . '\\';
        }

        return $namespace;
    }
}
