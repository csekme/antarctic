<?php

declare(strict_types=1);

namespace Framework\Http;

use Framework\Logging\TraceIdHolder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Correlation ID middleware. Either accepts an inbound `X-Request-Id`
 * (typically set by the load balancer / API gateway) or generates a fresh
 * 16-byte hex token. The id is:
 *
 *   - exposed as a `traceId` request attribute for handlers,
 *   - written into {@see TraceIdHolder} so {@see \Framework\Logging\TraceIdProcessor}
 *     can stamp every log record,
 *   - echoed back as the `X-Request-Id` response header.
 *
 * Accepted inbound ids are validated against a conservative whitelist
 * (`[A-Za-z0-9_.-]{1,128}`); anything else is replaced with a generated id
 * to prevent log-injection and header smuggling.
 */
final class TraceIdMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE = 'traceId';
    public const HEADER = 'X-Request-Id';

    private const ID_PATTERN = '/^[A-Za-z0-9_.\-]{1,128}$/';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $inbound = $request->getHeaderLine(self::HEADER);
        $traceId = $this->normalise($inbound) ?? $this->generate();

        TraceIdHolder::set($traceId);
        $request = $request->withAttribute(self::ATTRIBUTE, $traceId);

        try {
            $response = $handler->handle($request);
            if (!$response->hasHeader(self::HEADER)) {
                $response = $response->withHeader(self::HEADER, $traceId);
            }
            return $response;
        } finally {
            TraceIdHolder::clear();
        }
    }

    private function normalise(string $inbound): ?string
    {
        if ($inbound === '') {
            return null;
        }
        return preg_match(self::ID_PATTERN, $inbound) === 1 ? $inbound : null;
    }

    private function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
