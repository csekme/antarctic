<?php

declare(strict_types=1);

namespace Framework;

use Framework\Routing\Router as Router;
use Framework\Exceptions\PageNotFoundException as PageNotFoundException;

class Dispatcher
{

    public function __construct(private Router $router, private Container $container) {}


    /**
     * Handle the incoming request
     * @param Request request
     * @return Response response
     * @throws \Exception
     */
    public function handleRequest(Request $request)
    {

        $params = $this->router->match($request->uri);

        if ($params === false) {
            throw new \Exception(message: "No route matched for '$request->uri' with method '{$request->method}'", code: 404);
        }

        // Cross-site request forgery protection token
        if ($request->method ==  AbstractController::GET) {
            $token = new Token();
            $_SESSION['scrf'] = $token;
        } else {
            if (isset($_SESSION['scrf'])) {
                if (!isset($_POST['_scrf'])) {
                    throw new \Exception(message: "Method not allowed", code: 405);
                }
                $token = $_SESSION['scrf'];
                $value = $_POST['_scrf'];
                $check = new Token($value);
                if ($check->getHash() != $token->getHash()) {
                    throw new \Exception(message: "Method not allowed", code: 405);
                }
            }
        }

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
            $controller_object->setRequest($request);
            $controller_object->setResponse($this->container->get(Response::class));
            $action = $params['action'];
            $action = $this->convertToCamelCase($action);

            if (preg_match('/action$/i', $action) == 0) {
                $response = $controller_object->$action();
                $response->send();
            } else {
                throw new \Exception("Method $action in controller $controller cannot be called directly - remove the Action suffix to call this method");
            }
        } else {
            throw new \Exception("Controller class $controller not found");
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
    protected function convertToStudlyCaps($string)
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
    protected function convertToCamelCase($string)
    {
        return lcfirst($this->convertToStudlyCaps($string));
    }

    /**
     * Get the namespace for the controller class. The namespace defined in the
     * route parameters is added if present.
     *
     * @return string The request URL
     */
    protected function getNamespace($params, $namespace)
    {
        if (array_key_exists('namespace', $params)) {
            $namespace = $params['namespace'] . '\\';
        }

        return $namespace;
    }
}
