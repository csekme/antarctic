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

    public function getStatusCode(): int
    {
        return $this->status_code;
    }

    /**
     * @return string[] Raw "Header-Name: value" lines as queued via addHeader().
     */
    public function getHeaders(): array
    {
        return $this->headers;
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
            // `Set-Cookie` headers must NOT replace each other — a single
            // response may set multiple cookies (refresh + csrf + …). For
            // every other header the default replace=true is what we want.
            $isSetCookie = stripos($header, 'set-cookie:') === 0;
            header($header, !$isSetCookie);
        }

        echo $this->body;
    }
}