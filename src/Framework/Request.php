<?php

declare(strict_types=1);

namespace Framework;

class Request {
    public function __construct(public string $uri,
                                public string $method,
                                public array $get,
                                public array $post,
                                public array $files,
                                public array $cookie,
                                public array $server) {
    }

    public static function createFromGlobals() {
        return new static(
            $_SERVER["QUERY_STRING"],
            $_SERVER["REQUEST_METHOD"],
            $_GET,
            $_POST,
            $_FILES,
            $_COOKIE,
            $_SERVER
        );
    }
}