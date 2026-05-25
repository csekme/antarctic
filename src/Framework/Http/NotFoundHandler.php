<?php

declare(strict_types=1);

namespace Framework\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Terminal handler when no middleware produced a response.
 */
final class NotFoundHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(
            404,
            ['Content-Type' => 'application/json'],
            '{"error":{"code":"not_found","message":"No handler matched the request."}}',
        );
    }
}
