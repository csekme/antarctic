<?php

declare(strict_types=1);

namespace Framework;

use Framework\Config;

abstract class AbstractController {

    # The GET method requests a representation of the specified resource. Requests using GET should only retrieve data.
    const GET     = "GET";
    # The HEAD method asks for a response identical to a GET request, but without the response body.
    const HEAD    = "HEAD";
    # The POST method submits an entity to the specified resource, often causing a change in state or side effects on the server.
    const POST    = "POST";
    # The PUT method replaces all current representations of the target resource with the request payload.
    const PUT     = "PUT";
    # The DELETE method deletes the specified resource.
    const DELETE  = "DELETE";
    # The CONNECT method establishes a tunnel to the server identified by the target resource.
    const CONNECT = "CONNECT";
    # The OPTIONS method describes the communication options for the target resource.
    const OPTIONS = "OPTIONS";
    # The TRACE method performs a message loop-back test along the path to the target resource.
    const TRACE   = "TRACE";
    # The PATCH method applies partial modifications to a resource.
    const PATCH   = "PATH";

    protected Request $request;

    protected Response $response;

    /**
     * Parameters from the matched route. Populated by the Dispatcher and
     * available to action methods (and the legacy `__call` filter chain).
     *
     * @var array<string, mixed>
     */
    protected array $route_params = [];

    /**
     * Request, Response and route params are wired by the Dispatcher through
     * the container's `make()` call (M3.d). Concrete controllers can declare
     * additional constructor-injected services after these three — php-di
     * autowires them and passes the per-request values through by name.
     *
     * @param array<string, mixed> $route_params
     */
    public function __construct(Request $request, Response $response, array $route_params = [])
    {
        $this->request = $request;
        $this->response = $response;
        $this->route_params = $route_params;
    }

    /**
     * Redirect to a different page
     * @param string $url The relative URL
     * @return void
     */
    public function redirect($url)
    {
        header('Location: '.Config::get_server_protocol().'://'. $_SERVER['HTTP_HOST'] . $url, true, 303);
        exit;
    }

    protected function view(string $template, array $data): Response {
        $this->response->setBody(View::renderTemplate($template, $data));
        return $this->response;
    }

    protected function getResponse(): Response {
        return $this->response;
    }
}
