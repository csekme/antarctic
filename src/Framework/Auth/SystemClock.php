<?php

declare(strict_types=1);

namespace Framework\Auth;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Triviális PSR-20 óra implementáció. Tesztekben a {@see FrozenClock}-kal
 * cserélhető, hogy a JWT lejárati és iat claim-ek determinisztikusak
 * legyenek.
 */
final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
