<?php

declare(strict_types=1);

namespace Framework\Auth;

final class RefreshSessionResult
{
    public function __construct(
        public readonly RefreshSessionStatus $status,
        public readonly ?string $accessToken = null,
        public readonly ?string $refreshToken = null,
        public readonly ?int $accessTtl = null,
        public readonly ?string $reason = null,
    ) {
    }
}
