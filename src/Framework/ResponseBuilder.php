<?php

declare(strict_types=1);

namespace Framework;

class ResponseBuilder {
    private Response $response;

    public function __construct()
    {
        $this->response = new Response();
    }

    public static function create(): ResponseBuilder
    {
        return new self();
    }

    public function setBody(string $body): ResponseBuilder
    {
        $this->response->setBody($body);
        return $this;
    }

    public function addHeader(string $header): ResponseBuilder
    {
        $this->response->addHeader($header);
        return $this;
    }

    public function setStatusCode(int $code): ResponseBuilder
    {
        $this->response->setStatusCode($code);
        return $this;
    }

    public function build(): Response
    {
        return $this->response;
    }
}