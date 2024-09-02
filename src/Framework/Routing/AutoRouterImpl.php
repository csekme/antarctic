<?php

namespace Framework\Routing;
use Framework\Path as Path;
use Framework\ClassExploder as ClassExploder;

class AutoRouterImpl implements Router {

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
    public function add($route, $params = []) : void
    {
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
     * Load the routes from the routing table
     *
     * @return void
     */
    public function autoLoad()
    {
        $classExploder = new ClassExploder();
        $mapping = $classExploder->get_controller_mapping();
        foreach ($mapping as $path => $param) {
            // reflection api
            if (str_starts_with($path, "/")) {
                $path = substr($path, 1);
            }

            $className = $param['className'];
            $namespace = $param['nameSpace'];
            $fqClass = $namespace . '\\' . $className;
            if (class_exists($fqClass)) {
                $obj = new $fqClass([]);
                if (get_parent_class($obj) === 'Framework\RestController') {
                    # Ez egy RestController
                    $reflectionClass = new \ReflectionClass($obj::class);
                    $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
                    foreach ($methods as $method) {
                        $action = $method->name;
                        if (str_ends_with($action, "Action")) {
                            $action = substr($action, 0, strlen($action) - 6);
                        }
                        $attributes = $method->getAttributes(Path::class);
                        foreach ($attributes as $attribute) {
                            $attr = $attribute->newInstance();

                            $pathVariable = $attr->path;
                            $standard_rest = false;

                            if ($pathVariable == "") {
                                $standard_rest = true;
                                //  $pathVariable = $_SERVER["REQUEST_METHOD"]; //TODO: vizsgálni kellene az ütközést ha több metódust is így vesznek fel
                            } else {

                            if (!str_starts_with($pathVariable, "/")) {
                                $pathVariable = "/" . $pathVariable;
                            }
                        }

                            if (strpos($pathVariable, '{') !== false) {
                                $pathVariable = $this->convertPathPattern($pathVariable);
                            }

                            $this->add(
                                $path . $pathVariable,
                                [
                                    'controller' => $className,
                                    'namespace' => $param['nameSpace'],
                                    'action' => $action,
                                    'method' => $attr->method ?? 'GET',
                                    'standard_rest' => $standard_rest
                                ]
                            );
                        }
                    }
                } else if (get_parent_class($obj) === 'Framework\MvcController') {
                    # Ez egy MvcController
                    $reflectionClass = new \ReflectionClass($obj::class);
                    $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
                    foreach ($methods as $method) {
                        $action = $method->name;
                        if (str_ends_with($action, "Action")) {
                            $action = substr($action, 0, strlen($action) - 6);
                        }
                        $attributes = $method->getAttributes(Path::class);
                        foreach ($attributes as $attribute) {
                            $attr = $attribute->newInstance();
                            $pathVariable = $attr->path;
                            if (!str_starts_with($pathVariable, "/")) {
                                $pathVariable = "/" . $pathVariable;
                            }
                            $this->add(
                                $path . $pathVariable,
                                [
                                    'controller' => $className,
                                    'namespace' => $param['nameSpace'],
                                    'action' => $action,
                                    'method' => $attr->method ?? 'GET',
                                    'standard_rest' => false
                                ]
                            );
                        }
                    }
                }
            }
            $this->add($path, ['controller' => $param['className'], 'namespace' => $param['nameSpace']]);
        }
        $i = 1;
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
     * @param string $url The route URL
     *
     * @return boolean  true if a match found, false otherwise
     */
    public function match($url)
    {
        $requestMethod = $_SERVER['REQUEST_METHOD']; // Az aktuális HTTP-módszer lekérése
    
        foreach ($this->routes as $route => $params) {
            // Alapértelmezett érték hozzáadása a 'method' kulcshoz, ha nem létezik
            $routeMethod = $params['method'] ?? 'GET|POST|PUT|DELETE'; // Elfogadjon minden metódust, ha nincs meghatározva
    
            if (preg_match($route, $url, $matches) && preg_match("/$routeMethod/", $requestMethod)) {
                // Az egyeztetés már figyelembe veszi a request metódust is
                foreach ($matches as $key => $match) {
                    if (is_string($key)) {
                        $params[$key] = $match;
                    }
                }
    
                $this->params = $params;
                return true;
            }
        }
    
        return false;
    }
    


    function fix_post()
    {
        if (($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT') && empty($_POST)) {
            $data = file_get_contents('php://input');
            $_POST = json_decode($data);
        }
    }



    /**
     *  An example CORS-compliant method.  It will allow any GET, POST, or OPTIONS requests from any
     *  origin.
     *
     *  In a production environment, you probably want to be more restrictive, but this gives you
     *  the general idea of what is involved.  For the nitty-gritty low-down, read:
     *
     *  - https://developer.mozilla.org/en/HTTP_access_control
     *  - https://fetch.spec.whatwg.org/#http-cors-protocol
     *
     */
    function cors()
    {

        // Allow from any origin
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            // Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
            // you want to allow, and if so:
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');    // cache for 1 day
        }

        // Access-Control headers are received during OPTIONS requests
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                // may also be using PUT, PATCH, HEAD etc
                header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

            exit(0);
        }

        #echo "You have CORS!";
    }


    /**
     * Dispatch the route, creating the controller object and running the
     * action method

     * @return void
     */
    public function dispatch($url): void
    {
        $url = $this->removeQueryStringVariables($url);

        if ($this->match($url)) {
            $controller = $this->params['controller'];
            $controller = $this->convertToStudlyCaps($controller);
            $controller = $this->getNamespace() . $controller;

            if (class_exists($controller)) {    
                $controller_object = new $controller($this->params);
                $reflectionClass = new \ReflectionClass($controller_object::class);
                $action = '';
                if (isset($this->params['action'])) {
                    $action = $this->params['action'];
                    $action = $this->convertToCamelCase($action);
                }
                
                $request_method = $_SERVER['REQUEST_METHOD'];

                $found = false;

                if ($action == '') { //nincs action megpróbáljuk reflection a
                    $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
                    foreach ($methods as $method) {
                        $attributes = $method->getAttributes(Path::class);
                        foreach ($attributes as $attribute) {
                            $attr = $attribute->newInstance();
                            if ($attr->method !=null  && $attr->path == null) {
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
                        return;
                    }
                }

                // Ha a kérés nem GET metódusú akkor a body tartalmat adjuk át a metódusnak
                if ($request_method !== 'GET') {
                    if ($method->getNumberOfParameters() === 1) {
                        $controller_object->$action($_POST);
                    } else {
                        $controller_object->$action();
                    }
                } else {
                    if ($method->getNumberOfParameters() === 1) {
                        $methodParameters = $method->getParameters();
                        $value = $this->params[$methodParameters[0]->name];
                        $controller_object->$action($value);
                    } else {
                        $controller_object->$action();
                    }
                }
            } else {
                throw new \Exception("Controller class $controller not found");
            }
        } else {
            throw new \Exception('No route matched.', 404);
        }
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

    /**
     * Get the namespace for the controller class. The namespace defined in the
     * route parameters is added if present.
     *
     * @return string The request URL
     */
    protected function getNamespace()
    {
        $namespace = 'Application\Controllers\\';

        if (array_key_exists('namespace', $this->params)) {
            $namespace = $this->params['namespace'] . '\\';
        }

        return $namespace;
    }

    function handleRequest($url) : void
    {
        $this->fix_post();
        $this->cors();
        $this->dispatch($url);
    }
}