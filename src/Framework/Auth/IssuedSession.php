<?php

declare(strict_types=1);

namespace Framework\Auth;

final class IssuedSession
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $accessTtl,
        public readonly int $refreshTtl,
        public readonly array $roles,
    ) {
    }
}
