<?php

namespace Framework;
use \Framework\Config;

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

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
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
        $result = View::renderTemplate($template, $data);
        $this->response->setBody(View::renderTemplate($template, $data));
        return $this->response;
    }


}