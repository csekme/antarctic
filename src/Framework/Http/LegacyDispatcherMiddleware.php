<?php

declare(strict_types=1);

namespace Framework\Http;

use Framework\Dispatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Terminal middleware that hands the request to the legacy Dispatcher,
 * adapting the PSR-7 boundary on both sides. Acts as the rightmost step in
 * the pipeline until controllers consume PSR-7 directly.
 */
final class LegacyDispatcherMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Dispatcher $dispatcher)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $legacyRequest = HttpAdapter::toLegacyRequest($request);
        $legacyResponse = $this->dispatcher->handleRequest($legacyRequest);

        return HttpAdapter::toPsrResponse($legacyResponse);
    }
}
