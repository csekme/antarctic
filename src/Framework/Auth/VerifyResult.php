<?php

declare(strict_types=1);

namespace Framework\Auth;

final class VerifyResult
{
    public function __construct(
        public readonly VerifyStatus $status,
        public readonly ?int $userId = null,
    ) {
    }
}
