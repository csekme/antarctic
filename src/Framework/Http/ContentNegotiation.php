<?php

declare(strict_types=1);

namespace Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Cheap content negotiation. A request "wants JSON" when its Accept header
 * lists application/json (or +json variants) before text/html, or when the
 * request targets an /api/ path (SPA convention).
 */
final class ContentNegotiation
{
    public static function wantsJson(ServerRequestInterface $request): bool
    {
        $serverParams = $request->getServerParams();
        $uri = $serverParams['QUERY_STRING'] ?? $request->getUri()->getPath();
        if (str_starts_with(ltrim((string) $uri, '/'), 'api/')) {
            return true;
        }

        $accept = $request->getHeaderLine('Accept');
        if ($accept === '') {
            return false;
        }

        $jsonRank = self::rank($accept, ['application/json', 'application/problem+json', '+json']);
        $htmlRank = self::rank($accept, ['text/html', 'application/xhtml+xml']);

        if ($jsonRank === null) {
            return false;
        }
        if ($htmlRank === null) {
            return true;
        }
        return $jsonRank >= $htmlRank;
    }

    /**
     * Returns the highest q-value among needles found in the Accept header,
     * or null when none match. Defaults to q=1.0 when the Accept entry
     * omits an explicit quality parameter.
     *
     * @param list<string> $needles
     */
    private static function rank(string $accept, array $needles): ?float
    {
        $best = null;
        foreach (explode(',', $accept) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            $parts = array_map('trim', explode(';', $entry));
            $type = strtolower($parts[0]);
            $matched = false;
            foreach ($needles as $needle) {
                if (str_starts_with($needle, '+')) {
                    if (str_ends_with($type, $needle)) {
                        $matched = true;
                        break;
                    }
                } elseif ($type === $needle) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }
            $q = 1.0;
            for ($i = 1; $i < count($parts); $i++) {
                if (str_starts_with($parts[$i], 'q=')) {
                    $q = (float) substr($parts[$i], 2);
                }
            }
            if ($best === null || $q > $best) {
                $best = $q;
            }
        }
        return $best;
    }
}
