<?php

declare(strict_types=1);

namespace Framework\Http\RateLimit;

/**
 * Snapshot of a rate-limit bucket *after* the current request has been
 * counted. The middleware uses it to decide whether to allow or block, and to
 * emit the `X-RateLimit-*` headers and `Retry-After` on a 429.
 */
final class RateLimitState
{
    public function __construct(
        public readonly int $count,
        public readonly int $limit,
        public readonly int $resetsAt,
    ) {
    }

    public function isExceeded(): bool
    {
        return $this->count > $this->limit;
    }

    public function remaining(): int
    {
        return max(0, $this->limit - $this->count);
    }

    public function retryAfterSeconds(int $now): int
    {
        return max(0, $this->resetsAt - $now);
    }
}
