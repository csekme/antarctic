<?php

namespace Framework\Routing;
use Framework\ClassExploder;
use Framework\Path;
use ReflectionClass;
use Framework\Request;
use Framework\Response;
use Framework\Container;
use ReflectionException;
use ReflectionMethod;

/**
 * Router
 *
 * PHP version 8.0
 */
class StandardRouterImpl implements Router {


    /**
     * @throws ReflectionException
     */
    public function __construct()
    {
        $classExploder = new ClassExploder();
        $mapping = $classExploder->get_controller_mapping();
        foreach ($mapping as $path => $param) {
            if (str_starts_with($path, "/")) {
                $path = substr($path, 1);
            }
            $className = $param['className'];
            $namespace = $param['nameSpace'];
            $fullQualifiedClass = $namespace . '\\' . $className;
            if (class_exists($fullQualifiedClass)) {
                $controllerObj = new $fullQualifiedClass([]);
                $reflectionClass = new ReflectionClass($controllerObj::class);
                $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
                foreach ($methods as $method) {
                    $action = $method->name;
                    if (str_ends_with($action, "Action")) {
                      //  $action = substr($action, 0, strlen($action) - 6);
                    }
                    $attributes = $method->getAttributes(Path::class);
                    foreach ($attributes as $attribute) {
                        $attributeObj = $attribute->newInstance();
                        $pathVariable = $attributeObj->path;
                        $emptyPath = false;
                        if ($pathVariable == "") {
                            $emptyPath = true;
                        } else {
                            if ($path!='' && !str_starts_with($pathVariable, "/")) {
                                $pathVariable = "/" . $pathVariable;
                            } else if ($path=='' && str_starts_with($pathVariable, "/")) {
                                $pathVariable = substr($pathVariable, 1);
                            }
                        }
                        if (str_contains($pathVariable, '{')) {
                            $pathVariable = $this->convertPathPattern($pathVariable);
                        }
                        $this->add(
                            $path . $pathVariable,
                            [
                                'controller' => $className,
                                'namespace' => $param['nameSpace'],
                                'action' => $pathVariable == '' ? '': $action,
                                'method' => $attributeObj->method ?? 'GET',
                                'emptyPath' => $emptyPath
                            ]
                        );
                    }
                }

            }
        }
    }

    protected function convertPathPattern($path)
    {
        // Példa a konverzióra a '{id:\d+}' alapján
        $pattern = preg_replace_callback('/\{(\w+)(?::([^}]+))?\}/', function ($matches) {
            $varName = $matches[1];
            $regex = $matches[2] ?? '[^/]+';
            return '(?P<' . $varName . '>' . $regex . ')';
        }, $path);
        return $pattern;
    }

    /**
     * Associative array of routes (the routing table)
     * @var array
     */
    protected $routes = [];

    /**
     * Parameters from the matched route
     * @var array
     */
    protected $params = [];

    /**
     * Add a route to the routing table
     *
     * @param string $route  The route URL
     * @param array  $params Parameters (controller, action, etc.)
     *
     * @return void
     */
    public function add($route, $params = []): void {
        // Convert the route to a regular expression: escape forward slashes
        $route = preg_replace('/\//', '\\/', $route);

        // Convert variables e.g. {controller}
        $route = preg_replace('/\{([a-z]+)\}/', '(?P<\1>[a-z-]+)', $route);

        // Convert variables with custom regular expressions e.g. {id:\d+}
        $route = preg_replace('/\{([a-z]+):([^\}]+)\}/', '(?P<\1>\2)', $route);

        // Add start and end delimiters, and case insensitive flag
        $route = '/^' . $route . '$/i';

        $this->routes[$route] = $params;
    }

    /**
     * Get all the routes from the routing table
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Match the route to the routes in the routing table, setting the $params
     * property if a route is found.
     *
     * @param string $url The route URL
     *
     * @return boolean  true if a match found, false otherwise
     */
    public function match($url) : array | bool
    {
        // actual request method
        //$requestMethod = $_SERVER['REQUEST_METHOD'];
        $url = $this->removeQueryStringVariables($url);
        foreach ($this->routes as $route => $params) {
            //$routeMethod = $params['method'] ?? 'GET|POST|PUT|DELETE';
            if (preg_match($route, $url, $matches) /*&& preg_match("/$routeMethod/", $requestMethod)*/) {
                // Get named capture group values
                foreach ($matches as $key => $match) {
                    if (is_string($key)) {
                        $params[$key] = $match;
                    }
                }

                $this->params = $params;
                return $this->params;
            }
        }

        return false;
    }

    /**
     * Get the currently matched parameters
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }
 

    /**
     * Remove the query string variables from the URL (if any). As the full
     * query string is used for the route, any variables at the end will need
     * to be removed before the route is matched to the routing table. For
     * example:
     *
     *   URL                           $_SERVER['QUERY_STRING']  Route
     *   -------------------------------------------------------------------
     *   localhost                     ''                        ''
     *   localhost/?                   ''                        ''
     *   localhost/?page=1             page=1                    ''
     *   localhost/posts?page=1        posts&page=1              posts
     *   localhost/posts/index         posts/index               posts/index
     *   localhost/posts/index?page=1  posts/index&page=1        posts/index
     *
     * A URL of the format localhost/?page (one variable name, no value) won't
     * work however. (NB. The .htaccess file converts the first ? to a & when
     * it's passed through to the $_SERVER variable).
     *
     * @param string $url The full URL
     *
     * @return string The URL with the query string variables removed
     */
    protected function removeQueryStringVariables($url)
    {
        if ($url != '') {
            $parts = explode('&', $url, 2);

            if (strpos($parts[0], '=') === false) {
                $url = $parts[0];
            } else {
                $url = '';
            }
        }

        return $url;
    }

}