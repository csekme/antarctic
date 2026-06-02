<?php

declare(strict_types=1);

namespace Framework\Auth;

final class DisableResult
{
    public function __construct(public readonly DisableStatus $status)
    {
    }
}
