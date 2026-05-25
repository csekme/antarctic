<?php

declare(strict_types=1);

namespace Framework\Http;

use Framework\Request as LegacyRequest;
use Framework\Response as LegacyResponse;
use Nyholm\Psr7\Response as PsrResponse;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Boundary adapter between PSR-7 and the framework's legacy Request/Response
 * objects. The pipeline speaks PSR-7; existing controllers still consume the
 * legacy API. This shim lets both coexist until controllers are migrated.
 */
final class HttpAdapter
{
    public static function toLegacyRequest(ServerRequestInterface $psrRequest): LegacyRequest
    {
        $serverParams = $psrRequest->getServerParams();

        // The legacy Request stores QUERY_STRING in the `uri` field — that is
        // what the router consumes after the .htaccess rewrite. Preserve it.
        $uri = $serverParams['QUERY_STRING'] ?? $psrRequest->getUri()->getQuery();

        $parsedBody = $psrRequest->getParsedBody();
        if (!is_array($parsedBody)) {
            $parsedBody = [];
        }

        return new LegacyRequest(
            $uri,
            $psrRequest->getMethod(),
            $psrRequest->getQueryParams(),
            $parsedBody,
            $psrRequest->getUploadedFiles(),
            $psrRequest->getCookieParams(),
            $serverParams,
        );
    }

    public static function toPsrResponse(LegacyResponse $legacyResponse): PsrResponse
    {
        $status = $legacyResponse->getStatusCode();
        if ($status === 0) {
            $status = 200;
        }

        $headers = [];
        foreach ($legacyResponse->getHeaders() as $rawHeader) {
            $parts = explode(':', $rawHeader, 2);
            if (count($parts) === 2) {
                $headers[trim($parts[0])][] = trim($parts[1]);
            }
        }

        return new PsrResponse($status, $headers, $legacyResponse->getBody());
    }
}
