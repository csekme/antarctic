<?php

declare(strict_types=1);

namespace Framework\Auth;

enum VerifyStatus
{
    case Ok;
    case Unknown;
    case Misconfigured;
}
