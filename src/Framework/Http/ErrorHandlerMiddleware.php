<?php

declare(strict_types=1);

namespace Framework\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Catch-all error boundary. Converts Throwables thrown anywhere downstream
 * into a Response, picking between RFC 7807 problem+json (for API/JSON
 * clients) and a minimal HTML body for everything else.
 *
 * Exception codes 4xx are echoed verbatim; everything else maps to 500.
 */
final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly bool $debug = false,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            $status = $this->mapStatus($e->getCode());

            if ($status >= 500) {
                $this->logger->error($e->getMessage(), [
                    'exception' => $e::class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return ContentNegotiation::wantsJson($request)
                ? $this->problemResponse($request, $e, $status)
                : $this->htmlResponse($e, $status);
        }
    }

    private function mapStatus(int $code): int
    {
        if ($code >= 400 && $code < 600) {
            return $code;
        }
        return 500;
    }

    private function problemResponse(ServerRequestInterface $request, Throwable $e, int $status): ResponseInterface
    {
        $payload = [
            'type' => 'about:blank',
            'title' => $this->reasonPhrase($status),
            'status' => $status,
            'detail' => $status >= 500 && !$this->debug ? 'Internal server error.' : $e->getMessage(),
            'instance' => (string) $request->getUri()->getPath(),
        ];

        if ($this->debug) {
            $payload['exception'] = $e::class;
            $payload['file'] = $e->getFile();
            $payload['line'] = $e->getLine();
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            $body = '{"type":"about:blank","title":"Internal Server Error","status":500}';
        }

        return new Response(
            $status,
            ['Content-Type' => 'application/problem+json; charset=utf-8'],
            $body,
        );
    }

    private function htmlResponse(Throwable $e, int $status): ResponseInterface
    {
        $title = htmlspecialchars($this->reasonPhrase($status), ENT_QUOTES, 'UTF-8');
        $detail = $this->debug
            ? '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>'
            : '';

        $html = "<!doctype html><meta charset=\"utf-8\"><title>{$status} {$title}</title>"
            . "<h1>{$status} {$title}</h1>{$detail}";

        return new Response(
            $status,
            ['Content-Type' => 'text/html; charset=utf-8'],
            $html,
        );
    }

    private function reasonPhrase(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            415 => 'Unsupported Media Type',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            default => 'Error',
        };
    }
}
