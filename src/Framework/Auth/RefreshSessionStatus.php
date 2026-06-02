<?php

declare(strict_types=1);

namespace Framework\Auth;

enum RefreshSessionStatus
{
    case Ok;
    case MissingCookie;
    case CsrfMismatch;
    case TokenUnknown;
    case UserInactive;
    case RotationFailed;
}
