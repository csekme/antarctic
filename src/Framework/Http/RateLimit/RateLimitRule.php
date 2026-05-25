<?php

declare(strict_types=1);

namespace Framework\Http\RateLimit;

/**
 * One rate-limit bucket: a path-prefix + identification strategy + threshold.
 * The first matching rule in the rule-set wins (see {@see RateLimitMiddleware}),
 * so callers should order narrower rules before catch-alls.
 *
 * `keyStrategy` values:
 *   - "ip"   — REMOTE_ADDR (X-Forwarded-For honoured if the {@see RateLimitMiddleware}
 *              was constructed with `trustProxy: true`).
 *   - "user" — authenticated user id from the request `authUser` attribute.
 *              Falls back to IP when the user is anonymous.
 */
final class RateLimitRule
{
    public const KEY_IP = 'ip';
    public const KEY_USER = 'user';

    public function __construct(
        public readonly string $pathPrefix,
        public readonly int $limit,
        public readonly int $window,
        public readonly string $keyStrategy = self::KEY_IP,
        public readonly ?string $name = null,
    ) {
    }

    public function matches(string $path): bool
    {
        return str_starts_with($path, $this->pathPrefix);
    }

    public function id(): string
    {
        return $this->name ?? sprintf('%s:%s:%d/%d', $this->pathPrefix, $this->keyStrategy, $this->limit, $this->window);
    }
}
