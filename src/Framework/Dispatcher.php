<?php

declare(strict_types=1);

namespace Framework;

use Exception;
use Framework\Auth\RequireAuth;
use Framework\Auth\RequireRole;
use Framework\Routing\MatchResult;
use Framework\Routing\Router as Router;
use Framework\Validation\RequestHydrator;
use Framework\Validation\Validatable;
use Psr\Container\ContainerInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Class Dispatcher, handles the incoming request and dispatches it to the appropriate controller
 * @package Framework
 * @category Framework
 * @version 1.0
 * @since 1.0
 * @author Krisztián Csekme
 * @license GNU GPL v3.0
 * @see Router
 * @see Container
 */
readonly class Dispatcher
{

    public function __construct(private Router $router, private ContainerInterface $container)
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
        $interceptors = Config::get_interceptors();
        $matchResult = $this->router->match($request->uri, $request->method);

        if ($matchResult->isMethodNotAllowed()) {
            $allow = implode(', ', $matchResult->allowedMethods);
            throw new Exception(
                message: "Method '{$request->method}' not allowed for '{$request->uri}'. Allowed: {$allow}",
                code: 405,
            );
        }
        if (!$matchResult->isFound()) {
            throw new Exception(message: "No route matched for '$request->uri' with method '{$request->method}'", code: 404);
        }

        $params = $matchResult->params;

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
                $this->processAnnotation($attribute, $controller_object, $request);
            }
            $controller_object->setRequest($request);
            $response = $this->container->get(Response::class);
            $controller_object->setResponse($response);

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
                $this->processAnnotation($attribute, $controller_object, $request);
            }

            foreach ($interceptors as $interceptor) {
                $int = 'Application\\'.$interceptor['name'];
                if (class_exists($int) && $interceptor['call-chain'] == 'before' && boolval($interceptor['enabled'])) {
                    $interceptor_object = new $int();
                    if ($interceptor_object instanceof InterceptorInterface) {
                        $interceptor_object->invoke($request, $controller_object->getResponse());
                    }
                }
            }

            $args = $this->resolveActionArgs($method, $request, $params);
            $response = $controller_object->$action(...$args);


            foreach ($interceptors as $interceptor) {
                $int = 'Application\\'.$interceptor['name'];
                if (class_exists($int) && $interceptor['call-chain'] == 'after' && boolval($interceptor['enabled'])) {
                    $interceptor_object = new $int();
                    if ($interceptor_object instanceof InterceptorInterface) {
                        $response = $interceptor_object->invoke($request, $response);
                    }
                }
            }
            // Emission moved to SapiEmitter in Bootstrap; Dispatcher just returns the Response.
        } else {
            throw new Exception("Controller class $controller not found");
        }

        return $response;
    }

    /**
     * Resolve the positional argument list for a controller action.
     *
     * Each parameter is filled in turn:
     *   1. If the type implements {@see Validatable} the body is hydrated
     *      and validated by {@see RequestHydrator} from `$request->getJson()`.
     *      Validation failures throw {@see \Framework\Validation\ValidationException}
     *      that the error-handler middleware maps to 422 problem+json.
     *   2. Otherwise, if the parameter name appears in the matched route
     *      params, the route value is passed through.
     *   3. As a legacy fallback (non-GET requests with a single untyped
     *      parameter), the raw `$_POST` superglobal is injected — this
     *      preserves the pre-M4.b dispatch behavior for plain HTML forms.
     *   4. Else the parameter default (or `null` when nullable) is used.
     *
     * @param array<string, mixed> $params route match params
     * @return list<mixed>
     */
    private function resolveActionArgs(ReflectionMethod $method, Request $request, array $params): array
    {
        $parameters = $method->getParameters();
        if ($parameters === []) {
            return [];
        }
        $args = [];
        $singleUntyped = count($parameters) === 1 && $parameters[0]->getType() === null;
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                if (is_subclass_of($className, Validatable::class)) {
                    /** @var RequestHydrator $hydrator */
                    $hydrator = $this->container->get(RequestHydrator::class);
                    $args[] = $hydrator->hydrate($className, $request->getJson());
                    continue;
                }
            }
            $name = $parameter->getName();
            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
                continue;
            }
            if ($singleUntyped && $request->method !== 'GET') {
                $args[] = $_POST;
                continue;
            }
            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
                continue;
            }
            $args[] = $parameter->allowsNull() ? null : null;
        }
        return $args;
    }

    /**
     * Cross-site request forgery protection
     * @param Request $request
     * @throws Exception
     */
    private function crossSiteRequestForgeryProtection(Request $request): void
    {
        // Bearer tokenes API kéréseknek nincs session-CSRF (a Bearer token
        // önmagában nem küldhető cross-site az SPA-n kívül). Az /api/v1/auth/refresh
        // a double-submit cookie-jával maga gondoskodik a CSRF védelemről.
        if (str_starts_with(ltrim((string) $request->uri, '/'), 'api/v1/')) {
            return;
        }

        if ($request->method == AbstractController::GET) {
            $token = new Token();
            $_SESSION['csrf'] = $token;
        } else {
            if (isset($_SESSION['csrf'])) {
                $value = null;
                if ($request->isContentTypeJson()) {
                    $CSRF = $request->getCSRFFromHeader();
                    if (!isset($CSRF)) {
                        throw new Exception(message: "Method not allowed", code: 405);
                    }
                    $value = $CSRF;
                } else {
                    if (!isset($_POST['_csrf'])) {
                        throw new Exception(message: "Method not allowed", code: 405);
                    }
                    $value = $_POST['_csrf'];
                }

                $token = $_SESSION['csrf'];
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
    private function processAnnotation(ReflectionAttribute $attribute, Controller $controller_object, Request $request): void
    {
        $attribute = $attribute->newInstance();

        if ($attribute instanceof RequireAuth) {
            $this->requireAuthenticatedUser($request);
            return;
        }

        if ($attribute instanceof RequireRole) {
            $user = $this->requireAuthenticatedUser($request);
            if (!$user->hasAnyRole($attribute->roles)) {
                throw new Exception('User does not have the required role.', 403);
            }
            return;
        }

    }

    private function requireAuthenticatedUser(Request $request): \Framework\Auth\AuthenticatedUser
    {
        if ($request->authUser !== null) {
            return $request->authUser;
        }
        $detail = $request->unauthenticatedReason ?? 'Missing or invalid Bearer token.';
        throw new Exception($detail, 401);
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
