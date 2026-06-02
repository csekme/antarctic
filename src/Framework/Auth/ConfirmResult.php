<?php

declare(strict_types=1);

namespace Framework\Auth;

final class ConfirmResult
{
    public function __construct(public readonly ConfirmStatus $status)
    {
    }
}
