<?php

declare(strict_types=1);

namespace Framework\Auth;

enum DisableStatus
{
    case Disabled;
    case WrongPassword;
    case UserMissing;
}
