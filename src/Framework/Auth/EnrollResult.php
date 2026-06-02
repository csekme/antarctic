<?php

declare(strict_types=1);

namespace Framework\Auth;

final class EnrollResult
{
    public function __construct(
        public readonly EnrollStatus $status,
        public readonly ?string $secret = null,
        public readonly ?string $otpauthUri = null,
        public readonly ?string $qrDataUri = null,
    ) {
    }
}
