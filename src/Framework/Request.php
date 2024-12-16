<?php

declare(strict_types=1);

namespace Framework;

class Request {

    public array $json = []; // Új tulajdonság a JSON adatok tárolására
    private AbstractController $controller;

    public function __construct(public string $uri,
                                public string $method,
                                public array $get,
                                public array $post,
                                public array $files,
                                public array $cookie,
                                public array $server) {
        // JSON adat beolvasása a kérés testéből
        $this->json = $this->parseJsonBody();
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

    // JSON adatok beolvasása a kérés testéből
    protected function parseJsonBody(): array {
        // Ellenőrizzük, hogy a kérés tartalom típusa JSON-e
        if ($this->isContentTypeJson()) {
            // php://input olvasása és JSON dekódolás
            $rawData = file_get_contents('php://input');
            $jsonData = json_decode($rawData, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $jsonData ?? [];
            }
        }
        return [];
    }

    public function isContentTypeJson(): bool {
        return isset($this->server['CONTENT_TYPE']) && $this->server['CONTENT_TYPE'] === 'application/json';
    }

    public function getCSRFFromHeader()
    {
        return $this->server['HTTP_X_CSRF_TOKEN'] ?? null;
    }

    // Kényelmes getter a JSON adatokhoz
    public function getJson(): array {
        return $this->json;
    }

    public function getController(): AbstractController
    {
        return $this->controller;
    }

    public function setController(AbstractController $controller): void
    {
        $this->controller = $controller;
    }


}
