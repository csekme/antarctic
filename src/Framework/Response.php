<?php

declare(strict_types=1);

namespace Framework;

class Response {
    private string $body = "";

    private array $headers = [];

    private int $status_code = 0;


    public static function json(array $data, int $code = 200): Response
    {
        $response = new Response();
        // Beállítjuk a JSON tartalmat és a státuszkódot
        $response->body = json_encode($data);
        $response->setStatusCode($code);

        // Ellenőrizzük, hogy nincs JSON hibakód
        if (json_last_error() !== JSON_ERROR_NONE) {
            $response->body = json_encode(['error' => 'Invalid JSON data']);
            $response->setStatusCode(500); // Ha hibás JSON adat, 500-as státuszkódot küldünk
        }

        // Beállítjuk a megfelelő fejlécet
        $response->addHeader('Content-Type: application/json');
        return $response;
    }

    public function setStatusCode(int $code): void
    {
        $this->status_code = $code;
    }
    
    public function redirect(string $url): void
    {
        $this->addHeader("Location: $url");
    }

    public function addHeader(string $header): void
    {
        $this->headers[] = $header;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }
    
    public function getBody(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        if ($this->status_code) {
        
            http_response_code($this->status_code);
        }

        foreach ($this->headers as $header) {

            header($header);
        }

        echo $this->body;
    }
}