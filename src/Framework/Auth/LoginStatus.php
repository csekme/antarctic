<?php

declare(strict_types=1);

namespace Framework\Auth;

enum LoginStatus
{
    case Ok;
    case TwoFactorRequired;
    case Unverified;
    case InvalidCredentials;
}
